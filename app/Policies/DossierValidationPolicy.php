<?php

namespace App\Policies;

use App\Enums\ValidationStatus;
use App\Models\DossierValidation;
use App\Models\User;

class DossierValidationPolicy
{
    /**
     * Seul le responsable sollicité décide, une seule fois.
     */
    public function decide(User $user, DossierValidation $validation): bool
    {
        return $user->id === $validation->validator_id
            && $user->hasPermission('validate_dossier')
            && $validation->status === ValidationStatus::Pending;
    }
}
