<?php

namespace App\Policies;

use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Models\DossierRecipient;
use App\Models\User;
use App\Services\ValidationService;

/**
 * Actions sur le traitement individuel d'un destinataire : seul le
 * destinataire lui-même agit sur son traitement.
 */
class DossierRecipientPolicy
{
    public function respond(User $user, DossierRecipient $recipient): bool
    {
        return $user->id === $recipient->assigned_user_id
            && $user->hasPermission('respond_dossier')
            && $recipient->status->isOpen()
            && ! $recipient->hasPendingValidation();
    }

    /**
     * « Transmettre au responsable » : destinataire lui-même, traitement
     * ouvert, aucune validation en attente, et un responsable défini.
     */
    public function requestValidation(User $user, DossierRecipient $recipient): bool
    {
        return $user->id === $recipient->assigned_user_id
            && $user->hasPermission('transmit_dossier')
            && $recipient->status->isOpen()
            && ! $recipient->hasPendingValidation()
            && ValidationService::validatorFor($user) !== null;
    }

    /**
     * Réouverture par le responsable du département du destinataire.
     * Impossible sur une demande externe déjà répondue officiellement.
     */
    public function reopen(User $user, DossierRecipient $recipient): bool
    {
        $dossier = $recipient->dossier;

        return $user->hasPermission('reopen_dossier')
            && $user->department_id === $recipient->department_id
            && in_array($recipient->status, [RecipientStatus::Answered, RecipientStatus::Archived], true)
            && ! ($dossier->isExternal() && in_array($dossier->status, [DossierStatus::Answered, DossierStatus::Archived], true));
    }

    /**
     * « Relancer » un destinataire dont le traitement est ouvert :
     * l'expéditeur, le RH (demande externe) ou le responsable du département.
     */
    public function remind(User $user, DossierRecipient $recipient): bool
    {
        $dossier = $recipient->dossier;

        if (! $user->hasPermission('send_reminder') || ! $recipient->status->isOpen() || $user->id === $recipient->assigned_user_id) {
            return false;
        }

        return $dossier->created_by === $user->id
            || ($dossier->isExternal() && $user->hasPermission('manage_external_requests'))
            || ($user->hasPermission('view_department_dossiers') && $user->department_id === $recipient->department_id);
    }

    public function forward(User $user, DossierRecipient $recipient): bool
    {
        return $user->id === $recipient->assigned_user_id
            && $user->hasPermission('transmit_dossier')
            && $recipient->status->isOpen();
    }

    public function archive(User $user, DossierRecipient $recipient): bool
    {
        return $user->id === $recipient->assigned_user_id
            && $user->hasPermission('archive_dossier')
            && $recipient->status === RecipientStatus::Answered
            && ! $recipient->dossier->isExternal();
    }
}
