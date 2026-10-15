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
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $isExternal = $this->route('recipient')->dossier->isExternal();

        return [
            'content' => ['required', 'string', 'max:10000'],
            'decision' => [$isExternal ? 'required' : 'nullable', Rule::enum(ResponseDecision::class)],
            'justification' => ['nullable', 'string', 'max:5000'],
            'recommendation' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => DocumentStorage::uploadRules(),
        ];
    }
}
