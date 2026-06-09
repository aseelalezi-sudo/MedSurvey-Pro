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
        return [
            'surveyId' => ['required', 'string', 'max:50'],
            'answers' => ['required', 'array', 'max:300'],
            'department' => ['required', 'string', 'max:120'],
            'patientInfo' => ['nullable', 'array'],
            'patientInfo.name' => ['nullable', 'string', 'max:120'],
            'patientInfo.phone' => ['nullable', 'string', 'max:40'],
            'patientInfo.ageGroup' => ['nullable', 'string', 'max:80'],
            'patientInfo.gender' => ['nullable', 'string', 'max:40'],
            'patientInfo.visitType' => ['nullable', 'string', 'max:80'],
        ];
    }
}
