<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Model;

class SurveyQuestion extends Model
{
    use UsesCuid;

    protected $table = 'survey_questions';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sectionId',
        'type',
        'title',
        'description',
        'required',
        'category',
        'options',
        'followUp',
        'sortOrder',
    ];

    protected $casts = [
        'required' => 'boolean',
        'options' => 'array',
        'followUp' => 'array',
    ];

    public function section()
    {
        return $this->belongsTo(SurveySection::class, 'sectionId');
    }

    public function surveyAnswers()
    {
        return $this->hasMany(SurveyAnswer::class, 'questionId');
    }
}
