<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Seuls la photo, le téléphone et l'e-mail interne sont modifiables par
 * l'utilisateur lui-même : département, rôle et fonction ne sont pas acceptés.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +().-]+$/'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user())],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['boolean'],
        ];
    }

    /**
     * Photo trop lourde : même message, que le fichier soit arrêté par la
     * limite d'envoi de PHP (« uploaded ») ou par la règle des 2 Mo.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.uploaded' => self::PHOTO_TOO_LARGE,
            'photo.max' => self::PHOTO_TOO_LARGE,
            'photo.image' => 'La photo doit être une image JPG, PNG ou WebP.',
            'photo.mimes' => 'La photo doit être une image JPG, PNG ou WebP.',
        ];
    }

    public const PHOTO_TOO_LARGE = 'La photo est trop lourde : 2 Mo maximum.';
}
