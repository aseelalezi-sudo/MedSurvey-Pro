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
        'collectorId',
    ];

    protected $casts = [
        'answers' => 'array',
        'submittedAt' => 'datetime',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class, 'surveyId');
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collectorId');
    }

    public function ticket()
    {
        return $this->hasOne(Ticket::class, 'responseId');
    }

    public function surveyAnswers()
    {
        return $this->hasMany(SurveyAnswer::class, 'responseId');
    }

    public function scopeForUserAccess($query, ?User $user)
    {
        return $query
            ->when($user?->tenantId, fn ($q) => $q->where($q->qualifyColumn('tenantId'), $user->tenantId))
            ->when(
                $user?->role === 'head_of_department' && $user?->department,
                fn ($q) => $q->where($q->qualifyColumn('department'), $user->department)
            )
            ->when(
                $user?->role === 'staff',
                fn ($q) => $q->where($q->qualifyColumn('submittedAt'), '>=', now()->startOfDay())
            );
    }
}
