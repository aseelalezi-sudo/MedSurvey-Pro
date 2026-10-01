<?php

namespace App\Licensing\Services;

use App\Licensing\Support\ServerResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the LicenseHub public API. All parsing of the uniform
 * JSON envelope ({success, data} / {success, error:{message,code}}) happens
 * here so higher layers only see ServerResponse values.
 */
class LicenseClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $clientName,
        private readonly string $clientVersion,
        private readonly int $timeout,
    ) {}

    public function activate(
        string $licenseKey,
        string $fingerprint,
        ?string $deviceName,
        ?string $platform,
        ?string $ip,
        ?string $userAgent,
    ): ServerResponse {
        return $this->request('post', '/licenses/activate', [
            'license_key' => $licenseKey,
            'device' => [
                'fingerprint' => $fingerprint,
                'name' => $deviceName,
                'platform' => $platform ?? 'web',
            ],
            'client' => [
                'name' => $this->clientName,
                'version' => $this->clientVersion,
            ],
        ], $ip, $userAgent);
    }

    public function validate(
        string $licenseKey,
        string $fingerprint,
        ?string $ip,
        ?string $userAgent,
    ): ServerResponse {
        return $this->request('post', '/licenses/validate', [
            'license_key' => $licenseKey,
            'device' => ['fingerprint' => $fingerprint],
        ], $ip, $userAgent);
    }

    public function heartbeat(
        string $licenseKey,
        string $fingerprint,
        ?string $ip,
        ?string $userAgent,
    ): ServerResponse {
        return $this->request('post', '/licenses/heartbeat', [
            'license_key' => $licenseKey,
            'device' => ['fingerprint' => $fingerprint],
        ], $ip, $userAgent);
    }

    public function deactivate(
        string $licenseKey,
        string $fingerprint,
        ?string $ip,
        ?string $userAgent,
    ): ServerResponse {
        return $this->request('post', '/licenses/deactivate', [
            'license_key' => $licenseKey,
            'device' => ['fingerprint' => $fingerprint],
        ], $ip, $userAgent);
    }

    public function keys(?string $ip, ?string $userAgent): ServerResponse
    {
        return $this->request('get', '/keys', null, $ip, $userAgent);
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function request(string $method, string $path, ?array $body, ?string $ip, ?string $userAgent): ServerResponse
    {
        $url = $this->baseUrl.$path;

        try {
            $http = Http::timeout($this->timeout)
                ->acceptJson()
                ->withHeaders([
                    'X-Device-IP' => $ip,
                    'User-Agent' => trim(($userAgent ?: 'licensehub-sdk').' '.($ip ? "($ip)" : '')),
                ]);

            $response = $method === 'get' ? $http->get($url) : $http->post($url, $body ?? []);

            $payload = $response->json();

            if ($response->successful() && is_array($payload)) {
                return ServerResponse::ok($response->status(), $payload['data'] ?? $payload);
            }

            $code = $payload['error']['code'] ?? null;
            $message = $payload['error']['message'] ?? null;

            return ServerResponse::failure($response->status(), is_string($code) ? $code : null, is_string($message) ? $message : null);
        } catch (ConnectionException $e) {
            return ServerResponse::transportError($e->getMessage());
        } catch (\Throwable $e) {
            return ServerResponse::transportError($e->getMessage());
        }
    }
}
