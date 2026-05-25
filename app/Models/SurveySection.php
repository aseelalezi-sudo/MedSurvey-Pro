<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Model;

class SurveySection extends Model
{
    use UsesCuid;

    protected $table = 'survey_sections';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'surveyId', 'title', 'description', 'icon', 'sortOrder'];

    public function survey()
    {
        return $this->belongsTo(Survey::class, 'surveyId');
    }

    public function questions()
    {
        return $this->hasMany(SurveyQuestion::class, 'sectionId')->orderBy('sortOrder');
    }
}
