<?php

namespace App\Policies;

use App\Models\Response;
use App\Models\User;

/**
 * Un destinataire ne lit pas les réponses des autres destinataires.
 * L'expéditeur, le RH (demandes externes) et les responsables concernés
 * voient toutes les réponses.
 */
class ResponsePolicy
{
    public function view(User $user, Response $response): bool
    {
        $dossier = $response->dossier;

        if ($response->author_id === $user->id || $dossier->created_by === $user->id) {
            return true;
        }

        if ($user->hasPermission('view_all_dossiers')) {
            return true;
        }

        if ($user->hasPermission('manage_external_requests') && $dossier->isExternal()) {
            return true;
        }

        if ($response->is_final && $dossier->isParticipant($user)) {
            return true;
        }

        return $user->hasPermission('view_department_dossiers')
            && $user->department_id !== null
            && in_array($user->department_id, [$response->department_id, $dossier->originating_department_id], true);
    }
}
