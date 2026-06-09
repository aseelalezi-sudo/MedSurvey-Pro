<?php

namespace App\Models;

use App\Traits\UsesCuid;
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

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenantId');
    }
}
