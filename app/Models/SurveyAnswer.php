<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Model;

class SurveyAnswer extends Model
{
    use UsesCuid;

    protected $table = 'survey_answers';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'responseId', 'questionId', 'value'];

    public function response()
    {
        return $this->belongsTo(SurveyResponse::class, 'responseId');
    }

    public function question()
    {
        return $this->belongsTo(SurveyQuestion::class, 'questionId');
    }
}
