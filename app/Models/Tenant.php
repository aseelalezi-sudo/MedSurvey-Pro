<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use UsesCuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'isActive'];

    protected $casts = [
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
    ];

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenantId');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class, 'tenantId');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class, 'tenantId');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'tenantId');
    }

    /**
     * A tenant that still owns data cannot be deleted: the foreign keys would
     * null the tenantId of its users and surveys, turning them into global
     * records that every tenant-less administrator can see.
     */
    public function hasData(): bool
    {
        return $this->users()->exists()
            || $this->surveys()->exists()
            || $this->responses()->exists()
            || $this->tickets()->exists();
    }
}
