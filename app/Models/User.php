<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;
    use UsesCuid;

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

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
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
