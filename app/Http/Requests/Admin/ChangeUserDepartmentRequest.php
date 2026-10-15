<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChangeUserDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeDepartment', $this->route('user'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['username' => Str::lower(trim((string) $this->input('username')))]);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where('is_active', true),
                Rule::notIn([$this->route('user')->department_id]),
            ],
            'username' => StoreUserRequest::usernameRules(),
            'job_title' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_id.not_in' => 'Choisissez un département différent du département actuel.',
        ];
    }
}
