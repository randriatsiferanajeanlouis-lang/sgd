<?php

namespace App\Http\Requests\Concerns;

use App\Models\RequestType;
use Illuminate\Validation\Rule;

/**
 * Type « Autre » : l'expéditeur doit préciser le type de sa demande (champ request_type_other).
 * Pour tout autre type, la précision est effacée.
 */
trait PrecisesOtherRequestType
{
    protected function prepareForValidation(): void
    {
        $text = trim((string) $this->input('request_type_other'));

        $this->merge([
            'request_type_other' => RequestType::isOther($this->input('request_type_id')) && $text !== '' ? $text : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function otherRequestTypeRules(): array
    {
        return [
            'request_type_other' => [
                Rule::requiredIf(fn () => RequestType::isOther($this->input('request_type_id'))),
                'nullable',
                'string',
                'max:150',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function otherRequestTypeMessages(): array
    {
        return [
            'request_type_other.required' => 'Précisez le type de votre demande.',
            'request_type_other.max' => 'Le type de demande ne doit pas dépasser 150 caractères.',
        ];
    }
}
