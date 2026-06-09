<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['sometimes', 'boolean'],
            'requireName' => ['sometimes', 'boolean'],
            'requirePhone' => ['sometimes', 'boolean'],
            'assignedDepartments' => ['nullable', 'array'],
            'assignedDepartments.*' => ['string', 'max:200'],
            'tips' => ['nullable', 'array', 'max:20'],
            'tips.*' => ['nullable', 'string', 'max:500'],
            'sections' => ['nullable', 'array', 'max:50'],
            'sections.*.id' => ['nullable', 'string'],
            'sections.*.title' => ['nullable', 'string', 'max:300'],
            'sections.*.description' => ['nullable', 'string', 'max:1000'],
            'sections.*.icon' => ['nullable', 'string', 'max:50'],
            'sections.*.questions' => ['nullable', 'array', 'max:100'],
            'sections.*.questions.*.id' => ['nullable', 'string'],
            'sections.*.questions.*.type' => ['required', 'string'],
            'sections.*.questions.*.title' => ['required', 'string', 'max:500'],
            'sections.*.questions.*.description' => ['nullable', 'string', 'max:1000'],
            'sections.*.questions.*.required' => ['sometimes', 'boolean'],
            'sections.*.questions.*.category' => ['nullable', 'string', 'max:100'],
            'sections.*.questions.*.options' => ['nullable', 'array'],
            'sections.*.questions.*.followUp' => ['nullable', 'array'],
        ];
    }

    /**
     * Clean up the validated payload.
     *
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        $payload = $this->validated();

        if (isset($payload['tips'])) {
            $payload['tips'] = array_values(array_filter(
                $payload['tips'],
                fn ($tip) => ! is_null($tip) && trim($tip) !== '',
            ));
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('عنوان الاستبيان مطلوب'),
            'sections.*.questions.*.type.required' => __('نوع السؤال مطلوب'),
            'sections.*.questions.*.title.required' => __('عنوان السؤال مطلوب'),
        ];
    }
}
