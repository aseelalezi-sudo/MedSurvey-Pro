<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;
    use Notifiable;
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
        return [
            'role' => $this->role,
            'department' => $this->department,
        ];
    }
}
