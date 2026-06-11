<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use UsesCuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'responseId',
        'department',
        'patientName',
        'patientPhone',
        'priority',
        'status',
        'description',
        'resolvedAt',
        'resolutionNotes',
        'assignedTo',
        'tenantId',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
        'resolvedAt' => 'datetime',
    ];

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    public function response()
    {
        return $this->belongsTo(SurveyResponse::class, 'responseId');
    }

    public function scopeForTenant(Builder $query, ?string $tenantId): Builder
    {
        if (! $tenantId) {
            return $query;
        }

        return $query->where(function (Builder $tenantQuery) use ($tenantId): void {
            $tenantQuery
                ->where('tenantId', $tenantId)
                ->orWhere(function (Builder $legacyQuery) use ($tenantId): void {
                    $legacyQuery
                        ->whereNull('tenantId')
                        ->whereHas('response', fn (Builder $responseQuery) => $responseQuery->where('tenantId', $tenantId));
                });
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenantId');
    }
}
