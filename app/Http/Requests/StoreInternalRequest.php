<?php

namespace App\Http\Requests;

use App\Enums\RequestScope;
use App\Http\Requests\Concerns\PrecisesOtherRequestType;
use App\Models\Dossier;
use App\Models\RequestType;
use App\Services\DocumentStorage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInternalRequest extends FormRequest
{
    use PrecisesOtherRequestType;

    public function authorize(): bool
    {
        return $this->user()->can('createInternal', Dossier::class);
    }

    /**
     * Destinataires : utilisateurs actifs, distincts, autres que l'expéditeur.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        // Type « formulaire complet » : urgence et description deviennent obligatoires.
        $complete = Rule::requiredIf(fn () => RequestType::requiresCompleteForm($this->input('request_type_id')));

        return [
            'subject' => ['required', 'string', 'max:255'],
            'request_type_id' => [
                'required',
                Rule::exists('request_types', 'id')->where('is_active', true)->whereIn('scope', [RequestScope::Internal->value, RequestScope::Both->value]),
            ],
            'urgency_level_id' => [$complete, 'nullable', Rule::exists('urgency_levels', 'id')->where('is_active', true)],
            'due_at' => ['required', 'date', 'after_or_equal:today'],
            'description' => [$complete, 'nullable', 'string', 'max:10000'],
            'recipients' => ['required', 'array', 'min:1', 'max:50'],
            'recipients.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('is_active', true)->whereNotNull('department_id'),
                Rule::notIn([$this->user()->id]),
            ],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => DocumentStorage::uploadRules(),
            ...$this->otherRequestTypeRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipients.required' => 'Choisissez au moins un destinataire.',
            'recipients.*.not_in' => 'Vous ne pouvez pas vous envoyer une demande à vous-même.',
            'due_at.after_or_equal' => 'La date d’échéance ne peut pas être passée.',
            'urgency_level_id.required' => 'L’urgence est obligatoire pour ce type de demande.',
            'description.required' => 'La description est obligatoire pour ce type de demande.',
            ...$this->otherRequestTypeMessages(),
        ];
    }
}
