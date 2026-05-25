<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use UsesCuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title',
        'description',
        'isActive',
        'requireName',
        'requirePhone',
        'assignedDepartments',
        'tips',
        'tenantId',
    ];

    protected $casts = [
        'isActive' => 'boolean',
        'requireName' => 'boolean',
        'requirePhone' => 'boolean',
        'assignedDepartments' => 'array',
        'tips' => 'array',
        'createdAt' => 'datetime',
    ];

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    public function sections()
    {
        return $this->hasMany(SurveySection::class, 'surveyId')->orderBy('sortOrder');
    }

    public function responses()
    {
        return $this->hasMany(SurveyResponse::class, 'surveyId');
    }
}
