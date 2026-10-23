<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PrecisesOtherRequestType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDossierRequest extends FormRequest
{
    use PrecisesOtherRequestType;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('dossier'));
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $dossier = $this->route('dossier');

        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'request_type_id' => ['required', Rule::exists('request_types', 'id')->where('is_active', true)],
            'urgency_level_id' => [$dossier->isExternal() ? 'required' : 'nullable', Rule::exists('urgency_levels', 'id')->where('is_active', true)],
            'due_at' => [$dossier->isExternal() ? 'prohibited' : 'required', 'date', 'after_or_equal:today'],
            'comment' => ['nullable', 'string', 'max:1000'],
            ...$this->otherRequestTypeRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_at.after_or_equal' => 'La date d’échéance ne peut pas être passée.',
            ...$this->otherRequestTypeMessages(),
        ];
    }
}
