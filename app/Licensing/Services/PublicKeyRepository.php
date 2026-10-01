<?php

namespace App\Licensing\Services;

use App\Licensing\Models\LicenseState;
use App\Licensing\Support\ServerResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Fetches and caches LicenseHub public signing keys. Keys are cached on disk
 * (and mirrored into the state row) so sharp offline verification still works
 * for tokens issued earlier; a refresh only runs when the cache is missing or
 * stale, or a token references a kid we do not yet hold.
 */
class PublicKeyRepository
{
    private const CACHE_KEY = 'licensing.public_keys';

    public function keyFor(LicenseState $state, string $kid): ?string
    {
        $keyring = $this->keyringFor($state);

        return isset($keyring[$kid]) ? (string) $keyring[$kid] : null;
    }

    public function refresh(LicenseState $state, ServerResponse $response): void
    {
        $keyring = [];

        foreach ($response->data['keys'] ?? [] as $entry) {
            $k = $entry['kid'] ?? null;
            $pk = $entry['public_key'] ?? null;
            if (is_string($k) && is_string($pk) && $pk !== '') {
                $keyring[$k] = $pk;
            }
        }

        if ($keyring === []) {
            return;
        }

        $state->forceFill(['public_keys' => $keyring])->save();
        Cache::forever(self::CACHE_KEY, $keyring);
    }

    private function keyringFor(LicenseState $state): array
    {
        if (is_array($state->public_keys) && $state->public_keys !== []) {
            return $state->public_keys;
        }

        $cached = Cache::get(self::CACHE_KEY, []);
        $cached = is_array($cached) ? $cached : [];

        if ($cached !== []) {
            $state->forceFill(['public_keys' => $cached])->save();

            return $cached;
        }

        return [];
    }
}
