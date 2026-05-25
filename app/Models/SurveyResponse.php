<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    use UsesCuid;

    protected $table = 'survey_responses';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'surveyId',
        'answers',
        'patientName',
        'patientPhone',
        'ageGroup',
        'gender',
        'visitType',
        'department',
        'overallScore',
        'submittedAt',
        'tenantId',
    ];

    protected $casts = [
        'answers' => 'array',
        'submittedAt' => 'datetime',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class, 'surveyId');
    }

    public function ticket()
    {
        return $this->hasOne(Ticket::class, 'responseId');
    }

    public function surveyAnswers()
    {
        return $this->hasMany(SurveyAnswer::class, 'responseId');
    }
}
