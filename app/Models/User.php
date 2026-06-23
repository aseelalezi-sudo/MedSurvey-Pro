<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;
    use UsesCuid;

    protected static function booted(): void
    {
        static::created(function (User $user) {
            if ($user->role) {
                // Ensure the role exists before assigning it to avoid exceptions during tests that might not have seeded roles
                try {
                    $user->assignRole($user->role);
                } catch (\Exception $e) {
                    // Ignore missing role exception
                }
            }
        });

        static::updated(function (User $user) {
            if ($user->wasChanged('role') && $user->role) {
                try {
                    $user->syncRoles([$user->role]);
                } catch (\Exception $e) {
                    // Ignore missing role exception
                }
            }
        });
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'username',
        'password',
        'name',
        'email',
        'role',
        'department',
        'lastLogin',
        'isActive',
        'avatar',
        'tenantId',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
        'lastLogin' => 'datetime',
        'isActive' => 'boolean',
    ];

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'userId');
    }

    public function collectedResponses()
    {
        return $this->hasMany(SurveyResponse::class, 'collectorId');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenantId');
    }

    public function toFormattedArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'department' => $this->department,
            'createdAt' => optional($this->createdAt)->toISOString(),
            'lastLogin' => optional($this->lastLogin)->toISOString(),
            'isActive' => (bool) $this->isActive,
            'avatar' => $this->avatar,
            'tenantId' => $this->tenantId,
        ];
    }
}
