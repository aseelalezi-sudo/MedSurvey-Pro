<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('id');

        return [
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($userId)->whereNull('deleted_at')],
            'password' => ['nullable', 'string', Password::min(8)->letters()->numbers()],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff'])],
            'department' => ['nullable', 'string', 'max:200'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => __('اسم المستخدم مطلوب'),
            'username.unique' => __('اسم المستخدم مستخدم بالفعل'),
            'name.required' => __('الاسم مطلوب'),
            'role.required' => __('الدور مطلوب'),
        ];
    }
}
