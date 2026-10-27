<?php

namespace App\Http\Requests;

use App\Enums\ResponseDecision;
use App\Services\DocumentStorage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Réponse d'un destinataire. Pour une demande externe, la décision
 * (positive/négative) est obligatoire.
 */
class RespondRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('respond', $this->route('recipient'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required_without' => 'Choisissez Positive ou Négative, ou écrivez une réponse.',
            'decision.required_without' => 'Choisissez Positive ou Négative, ou écrivez une réponse.',
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $isExternal = $this->route('recipient')->dossier->isExternal();

        return [
            // Demande externe : décision et commentaire obligatoires. Demande interne : au moins l'un des deux
            // (un simple choix Positive / Négative, ou un texte seul, ou les deux).
            'content' => [$isExternal ? 'required' : 'required_without:decision', 'nullable', 'string', 'max:10000'],
            'decision' => [$isExternal ? 'required' : 'required_without:content', 'nullable', Rule::enum(ResponseDecision::class)],
            'justification' => ['nullable', 'string', 'max:5000'],
            'recommendation' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => DocumentStorage::uploadRules(),
        ];
    }
}
