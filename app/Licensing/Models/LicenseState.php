<?php

namespace App\Licensing\Models;

use App\Licensing\Enums\LicenseStatus;
use App\Support\Cuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Persisted licensing state for a single installation + product.
 *
 * Sensitive material (license key, token, fingerprint) is encrypted at rest
 * with the application key. The signed token and the public-key keyring are
 * what allow the app to keep working fully offline within its grace window.
 */
class LicenseState extends Model
{
    protected $table = 'license_states';

    public $incrementing = false;

    public $timestamps = true;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'product',
        'status',
        'license_key',
        'fingerprint',
        'token',
        'payload',
        'public_keys',
        'token_expires_at',
        'activated_at',
        'last_heartbeat_at',
        'last_validated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'payload' => 'encrypted:array',
            'public_keys' => 'array',
            'license_key' => 'encrypted',
            'fingerprint' => 'encrypted',
            'token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'last_validated_at' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LicenseState $state): void {
            if (! $state->getKey()) {
                $state->{$state->getKeyName()} = Cuid::make();
            }
        });
    }
}
