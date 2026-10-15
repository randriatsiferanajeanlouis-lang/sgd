<?php

namespace App\Http\Requests;

use App\Enums\ReceptionChannel;
use App\Enums\RequestScope;
use App\Models\Dossier;
use App\Models\RequestType;
use App\Services\DocumentStorage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'une demande externe : réservé au RH (create_external_request).
 * L'échéance n'est pas saisie : elle est calculée à partir de l'urgence.
 */
class StoreExternalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createExternal', Dossier::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        // Type « formulaire complet » (ex. Sponsoring) : organisme, contact et description obligatoires.
        $complete = Rule::requiredIf(fn () => RequestType::requiresCompleteForm($this->input('request_type_id')));

        return [
            'requester_name' => ['required', 'string', 'max:255'],
            'requester_organization' => [$complete, 'nullable', 'string', 'max:255'],
            'requester_phone' => [$complete, 'nullable', 'string', 'max:50', 'regex:/^[0-9 +().-]+$/'],
            'requester_email' => [$complete, 'nullable', 'email', 'max:255'],
            'request_type_id' => [
                'required',
                Rule::exists('request_types', 'id')->where('is_active', true)->whereIn('scope', [RequestScope::External->value, RequestScope::Both->value]),
            ],
            'subject' => ['required', 'string', 'max:255'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'reception_channel' => ['required', Rule::enum(ReceptionChannel::class)],
            'urgency_level_id' => ['required', Rule::exists('urgency_levels', 'id')->where('is_active', true)],
            'description' => [$complete, 'nullable', 'string', 'max:10000'],
            'recipients' => ['required', 'array', 'min:1', 'max:20'],
            'recipients.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('is_active', true)->whereNotNull('department_id'), Rule::notIn([$this->user()->id])],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => DocumentStorage::uploadRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipients.required' => 'Choisissez au moins un responsable ou utilisateur destinataire.',
            'received_at.before_or_equal' => 'La date de réception ne peut pas être dans le futur.',
            'requester_phone.regex' => 'Le numéro de téléphone contient des caractères invalides.',
            'requester_organization.required' => 'L’organisme est obligatoire pour ce type de demande.',
            'requester_phone.required' => 'Le téléphone est obligatoire pour ce type de demande.',
            'requester_email.required' => 'L’e-mail est obligatoire pour ce type de demande.',
            'description.required' => 'La description est obligatoire pour ce type de demande.',
        ];
    }
}
