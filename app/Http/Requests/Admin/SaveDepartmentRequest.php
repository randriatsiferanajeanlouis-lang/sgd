<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department
            ? $this->user()->can('update', $department)
            : $this->user()->can('create', Department::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => Str::upper(Str::slug((string) $this->input('code'), '_')),
        ]);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $department = $this->route('department');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($department)],
            'code' => ['required', 'string', 'max:30', Rule::unique('departments', 'code')->ignore($department)],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'manager_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('is_active', true)->where('department_id', $department?->id ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manager_id.exists' => 'Le responsable doit être un compte actif de ce département.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nom', 'manager_id' => 'responsable'];
    }
}
