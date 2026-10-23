<?php

namespace App\Http\Requests;

use App\Enums\ReceptionChannel;
use App\Services\DocumentStorage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOfficialResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordOfficialResponse', $this->route('dossier'));
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000'],
            'channel' => ['required', Rule::enum(ReceptionChannel::class)],
            'sent_at' => ['required', 'date', 'before_or_equal:today'],
            'document' => ['nullable', ...DocumentStorage::uploadRules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sent_at.before_or_equal' => 'La date d’envoi ne peut pas être dans le futur.',
        ];
    }
}
