<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitSurveyResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'surveyId' => ['required', 'string', 'max:50'],
            'clientResponseId' => ['nullable', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            '_timingToken' => ['nullable', 'string', 'max:2048'],
            'answers' => ['required', 'array', 'max:300'],
            'department' => ['required', 'string', 'max:120'],
            'patientInfo' => ['nullable', 'array'],
            'patientInfo.name' => ['nullable', 'string', 'max:120'],
            'patientInfo.phone' => ['nullable', 'string', 'max:40'],
            'patientInfo.ageGroup' => ['nullable', 'string', 'max:80'],
            'patientInfo.gender' => ['nullable', 'string', 'max:40'],
            'patientInfo.visitType' => ['nullable', 'string', 'max:80'],
        ];

        // Mobile and the current web form send a list. Older clients send an
        // answer map keyed by question ID, which ResponseService also validates.
        $answers = $this->input('answers');
        if (is_array($answers) && array_is_list($answers)) {
            $rules['answers.*'] = ['array'];
            $rules['answers.*.questionId'] = ['required', 'string', 'max:50'];
            $rules['answers.*.value'] = ['nullable'];
        }

        return $rules;
    }
}
