<?php

namespace App\Licensing\Services;

use App\Licensing\Enums\LicenseStatus;
use App\Licensing\Models\LicenseState;
use App\Licensing\Support\LicenseCheck;
use App\Licensing\Support\ServerResponse;
use App\Licensing\Support\Token;
use DateTimeImmutable;

/**
 * Entry-point facade for the whole licensing SDK.
 *
 * This orchestrates everything: reading the persisted state, verifying the
 * signed token locally (fully offline within the grace window), reaching the
 * license server when needed, mapping every outcome to a LicenseCheck, and
 * keeping the feature flags visible to the application.
 */
class LicenseService
{
    private ?LicenseState $resolvedState = null;

    public function __construct(
        private readonly LicenseClient $client,
        private readonly SignatureVerifier $verifier,
        private readonly DeviceIdentity $device,
        private readonly PublicKeyRepository $keys,
    ) {}

    /**
     * Check licensing for this installation. A valid, unexpired, correctly
     * signed token allows full offline operation; otherwise we fall back to
     * the network (validate -> activate) and finally to an explicit failure.
     */
    public function boot(): LicenseCheck
    {
        if (! config('licensing.enabled', true)) {
            return LicenseCheck::ok([], '');
        }

        $state = $this->state();
        $this->ensureFingerprint($state);

        $local = $this->verifyLocally($state);

        if ($local->valid()) {
            return $local;
        }

        if ($local->status === LicenseStatus::Invalid) {
            return $local;
        }

        if (! $this->hasLicenseKey($state)) {
            return LicenseCheck::fail(LicenseStatus::NotActivated, 'No license key is configured for this installation.');
        }

        return $this->refreshOnline($state);
    }

    /**
     * Force an online revalidation. Used by the license:validate command.
     */
    public function validate(): LicenseCheck
    {
        $state = $this->state();
        $this->ensureFingerprint($state);

        if (! $this->hasLicenseKey($state)) {
            return LicenseCheck::fail(LicenseStatus::NotActivated, 'No license key is configured for this installation.');
        }

        return $this->refreshOnline($state);
    }

    /**
     * Explicitly activate (first run or after the server no longer knows the
     * device). Does not require a pre-existing token.
     */
    public function activate(): LicenseCheck
    {
        $state = $this->state();
        $this->ensureFingerprint($state);

        $key = $state->license_key ?: (string) config('licensing.key', '');

        if ($key === '') {
            return LicenseCheck::fail(LicenseStatus::NotActivated, 'No license key is configured for this installation.');
        }

        $response = $this->client->activate(
            $key,
            $state->fingerprint,
            (string) config('app.name'),
            'web',
            $this->ip(),
            $this->userAgent(),
        );

        if ($response->transportError) {
            return LicenseCheck::fail(LicenseStatus::Offline, 'Cannot reach the license server.');
        }

        if ($response->ok) {
            return $this->applyIssueResponse($state, $response);
        }

        return $this->mapFailure($state, $response);
    }

    /**
     * Periodic online re-check so revocations are picked up promptly.
     */
    public function heartbeat(): LicenseCheck
    {
        $state = $this->state();
        $this->ensureFingerprint($state);

        $key = $state->license_key ?: config('licensing.key', '');

        if ($key === '') {
            return LicenseCheck::fail(LicenseStatus::NotActivated, 'No license key is configured for this installation.');
        }

        $response = $this->client->heartbeat($key, $state->fingerprint, $this->ip(), $this->userAgent());

        if ($response->transportError) {
            return LicenseCheck::fail(LicenseStatus::Error, 'Cannot reach the license server.', null);
        }

        if ($response->ok) {
            $state->forceFill(['last_heartbeat_at' => now()])->save();

            return $this->verifyLocally($state);
        }

        if ($response->code === 'forbidden' || $response->status === 403) {
            $this->markInvalid($state, LicenseStatus::Revoked, 'The license has been revoked or suspended.', $response->code);

            return LicenseCheck::fail(LicenseStatus::Revoked, 'The license has been revoked or suspended.');
        }

        return $this->verifyLocally($state);
    }

    /**
     * Free the device seat and clear local state. Called on deactivation.
     */
    public function deactivate(): void
    {
        $state = $this->stateOrNull();

        if ($state === null || ! $state->license_key || ! $state->fingerprint) {
            return;
        }

        $this->client->deactivate($state->license_key, $state->fingerprint, $this->ip(), $this->userAgent());

        $state->forceFill([
            'status' => LicenseStatus::NotActivated,
            'token' => null,
            'payload' => null,
            'token_expires_at' => null,
            'activated_at' => null,
            'last_validated_at' => null,
        ])->save();
    }

    /**
     * The currently persisted state row (query-able for dashboards). Returns
     * null when licensing has not been initialized with a key.
     */
    public function state(): LicenseState
    {
        if ($this->resolvedState !== null) {
            return $this->resolvedState;
        }

        $state = LicenseState::query()->firstOrCreate(
            ['product' => config('licensing.product', 'medsurvey-pro')],
            ['status' => LicenseStatus::NotActivated],
        );

        return $this->resolvedState = $state;
    }

    public function stateOrNull(): ?LicenseState
    {
        return $this->resolvedState ?? LicenseState::query()->where('product', config('licensing.product', 'medsurvey-pro'))->first();
    }

    private function hasLicenseKey(LicenseState $state): bool
    {
        return is_string($state->license_key) && $state->license_key !== ''
            || is_string(config('licensing.key', '')) && config('licensing.key') !== '';
    }

    private function ensureFingerprint(LicenseState $state): void
    {
        if (is_string($state->fingerprint) && $state->fingerprint !== '') {
            return;
        }

        $state->fingerprint = $this->device->generate();
        $state->save();
    }

    /**
     * Try to verify the persisted token without touching the network.
     */
    private function verifyLocally(LicenseState $state): LicenseCheck
    {
        $tokenValue = $state->token;

        if (! is_string($tokenValue) || $tokenValue === '') {
            return LicenseCheck::fail(LicenseStatus::NotActivated, 'This installation is not activated yet.');
        }

        try {
            $token = Token::parse($tokenValue);
        } catch (\Throwable $e) {
            $this->markInvalid($state, LicenseStatus::InvalidToken, 'Stored token is malformed.');

            return LicenseCheck::fail(LicenseStatus::InvalidToken, 'Stored token is malformed.');
        }

        if ($token->claim('prd') !== config('licensing.product', 'medsurvey-pro')) {
            $this->markInvalid($state, LicenseStatus::Invalid, 'Token product does not match this installation.');

            return LicenseCheck::fail(LicenseStatus::Invalid, 'Token product does not match this installation.');
        }

        $issuer = config('licensing.issuer', 'licensehub');
        if (is_string($token->claim('iss')) && $token->claim('iss') !== $issuer) {
            return LicenseCheck::fail(LicenseStatus::InvalidToken, 'Token issuer does not match.');
        }

        $kid = $token->kid();
        $publicKey = is_string($kid) ? $this->keys->keyFor($state, $kid) : null;

        if ($publicKey === null) {
            return LicenseCheck::fail(LicenseStatus::InvalidToken, 'Unknown signing key (kid).');
        }

        if (! $this->verifier->verify($token, $publicKey)) {
            $this->markInvalid($state, LicenseStatus::InvalidToken, 'Token signature is invalid.');

            return LicenseCheck::fail(LicenseStatus::InvalidToken, 'Token signature is invalid.');
        }

        $exp = $token->claim('exp');
        if (! is_int($exp) && ! (is_string($exp) && ctype_digit($exp))) {
            return LicenseCheck::fail(LicenseStatus::InvalidToken, 'Token is missing an expiration claim.');
        }

        if ((new DateTimeImmutable)->getTimestamp() >= (int) $exp) {
            return LicenseCheck::fail(LicenseStatus::Expired, 'The offline grace window has ended; the token is expired.');
        }

        if (! $this->device->matches((string) $token->claim('devf'), $state->fingerprint, (string) config('licensing.device_hash_secret', ''))) {
            return LicenseCheck::fail(LicenseStatus::InvalidToken, 'Token is bound to a different device.');
        }

        return LicenseCheck::ok((array) $token->payload, $tokenValue);
    }

    /**
     * Called when offline and token is expired: try a revalidation; if that
     * fails return the previous determination.
     */
    private function refreshOnline(LicenseState $state): LicenseCheck
    {
        $key = $state->license_key ?: config('licensing.key', '');

        if ($key === '') {
            return LicenseCheck::fail(LicenseStatus::NotActivated, 'No license key is configured for this installation.');
        }

        $state->license_key = $key;
        $response = $this->client->validate($key, $state->fingerprint, $this->ip(), $this->userAgent());

        if ($response->transportError) {
            return LicenseCheck::fail(LicenseStatus::Offline, 'Cannot reach the license server; no valid cached token is available.');
        }

        if ($response->ok) {
            return $this->applyIssueResponse($state, $response);
        }

        if ($response->code === 'not_found' || $response->status === 404) {
            return $this->activate();
        }

        return $this->mapFailure($state, $response);
    }

    private function applyIssueResponse(LicenseState $state, ServerResponse $response): LicenseCheck
    {
        $tokenValue = $response->data['token'] ?? null;

        if (! is_string($tokenValue) || $tokenValue === '') {
            return LicenseCheck::fail(LicenseStatus::Error, 'Server returned a success without a token.');
        }

        $state->license_key = $state->license_key ?: config('licensing.key', '');
        $state->token = $tokenValue;

        try {
            $token = Token::parse($tokenValue);
            $state->payload = $token->payload;
            $state->token_expires_at = isset($token->payload['exp']) ? date('Y-m-d H:i:s', (int) $token->payload['exp']) : null;
        } catch (\Throwable) {
            // persisted and re-verified below; a bad token will be rejected there
        }

        $state->status = LicenseStatus::Valid;
        $state->activated_at ??= now();
        $state->last_validated_at = now();
        $state->save();

        $this->refreshKeysOnline($state);

        return $this->verifyLocally($state);
    }

    private function refreshKeysOnline(LicenseState $state): void
    {
        $response = $this->client->keys($this->ip(), $this->userAgent());

        if ($response->ok) {
            $this->keys->refresh($state, $response);
        }
    }

    public function refreshKeys(): bool
    {
        $state = $this->state();
        $this->ensureFingerprint($state);
        $this->refreshKeysOnline($state);

        return is_array($state->public_keys) && $state->public_keys !== [];
    }

    private function mapFailure(LicenseState $state, ServerResponse $response): LicenseCheck
    {
        if ($response->status === 403 || $response->code === 'forbidden') {
            $this->markInvalid($state, LicenseStatus::Revoked, 'The license has been revoked or suspended.', $response->code);

            return LicenseCheck::fail(LicenseStatus::Revoked, 'The license has been revoked or suspended.');
        }

        if ($response->status === 429) {
            return LicenseCheck::fail(LicenseStatus::ActivationFailed, 'The license seat limit has been reached.');
        }

        return LicenseCheck::fail(LicenseStatus::ActivationFailed, $response->message ?? 'The license server rejected the request.', 400);
    }

    private function markInvalid(LicenseState $state, LicenseStatus $status, string $reason, ?string $serverCode = null): void
    {
        $state->status = $status;
        $state->save();
    }

    private function ip(): ?string
    {
        $request = request();

        return $request ? (string) $request->ip() : null;
    }

    private function userAgent(): ?string
    {
        $request = request();

        return $request ? (string) $request->userAgent() : null;
    }
}
