<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * Nom d'utilisateur : minuscules, sans espaces autour, unique.
     *
     * @return list<string>
     */
    public static function usernameRules(): array
    {
        return ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => Str::lower(trim((string) $this->input('username'))),
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => $this->filled('email') ? Str::lower(trim((string) $this->input('email'))) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'username' => self::usernameRules(),
            'department_id' => ['required', Rule::exists('departments', 'id')->where('is_active', true)],
            'job_title' => ['required', 'string', 'max:150'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +().-]+$/'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Le nom d’utilisateur ne peut contenir que des lettres minuscules, des chiffres, et les caractères . _ -',
        ];
    }
}
