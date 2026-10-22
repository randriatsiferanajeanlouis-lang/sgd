<?php

namespace App\Policies;

use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Models\Dossier;
use App\Models\User;

class DossierPolicy
{
    public function view(User $user, Dossier $dossier): bool
    {
        return $dossier->rememberAccess('view:'.$user->id, fn () => Dossier::query()->visibleTo($user)->whereKey($dossier->getKey())->exists());
    }

    public function createInternal(User $user): bool
    {
        // Une demande interne part toujours d'un département : le super administrateur n'en a pas.
        return $user->hasPermission('create_internal_request') && $user->department_id !== null;
    }

    /**
     * Seul le RH (permission create_external_request) enregistre une demande externe.
     */
    public function createExternal(User $user): bool
    {
        return $user->hasPermission('create_external_request');
    }

    /**
     * Modification (objet, description, type, urgence, échéance) : l'auteur,
     * tant que le dossier n'est ni répondu officiellement ni archivé.
     */
    public function update(User $user, Dossier $dossier): bool
    {
        return $dossier->created_by === $user->id
            && ! $dossier->isTrashedBySender()
            && ! in_array($dossier->status, [DossierStatus::Archived, $dossier->isExternal() ? DossierStatus::Answered : null], true);
    }

    /**
     * Mise à la corbeille : l'expéditeur d'une demande interne. Le dossier
     * quitte seulement son espace ; destinataires et historique le conservent.
     */
    public function delete(User $user, Dossier $dossier): bool
    {
        return $dossier->created_by === $user->id
            && ! $dossier->isExternal()
            && ! $dossier->isTrashedBySender();
    }

    /**
     * Restauration ou suppression définitive depuis la corbeille de l'expéditeur.
     */
    public function restore(User $user, Dossier $dossier): bool
    {
        return $dossier->created_by === $user->id
            && $dossier->isTrashedBySender()
            && $dossier->purged_by_sender_at === null;
    }

    public function forceDelete(User $user, Dossier $dossier): bool
    {
        return $this->restore($user, $dossier);
    }

    /**
     * Corbeille : ouverte à qui peut envoyer une demande interne.
     */
    public function viewTrash(User $user): bool
    {
        return $this->createInternal($user);
    }

    /**
     * Ajout de pièces : participants au dossier (auteur, destinataires, RH
     * pour une demande externe), tant que le dossier n'est pas archivé.
     */
    public function addDocument(User $user, Dossier $dossier): bool
    {
        return $dossier->status !== DossierStatus::Archived
            && ($dossier->isParticipant($user) || ($dossier->isExternal() && $user->hasPermission('manage_external_requests')));
    }

    /**
     * Archive ZIP des pièces actives accessibles.
     */
    public function downloadAll(User $user, Dossier $dossier): bool
    {
        return $user->hasPermission('download_document') && $this->view($user, $dossier);
    }

    /**
     * Réponse officielle au demandeur externe : RH uniquement, après au moins
     * une réponse d'un destinataire, et une seule fois.
     */
    public function recordOfficialResponse(User $user, Dossier $dossier): bool
    {
        return $dossier->isExternal()
            && $user->hasPermission('manage_external_requests')
            && in_array($dossier->status, [DossierStatus::Sent, DossierStatus::InProgress], true)
            && $dossier->recipients()->whereIn('status', [RecipientStatus::Answered->value, RecipientStatus::Archived->value])->exists();
    }

    public function archiveExternal(User $user, Dossier $dossier): bool
    {
        return $dossier->isExternal()
            && $user->hasPermission('manage_external_requests')
            && $user->hasPermission('archive_dossier')
            && $dossier->status === DossierStatus::Answered;
    }

    /**
     * Historique détaillé : volontairement indépendant de view. Un
     * administrateur voit les dossiers mais pas leur historique tant que son
     * rôle n'a aucune permission view_*_history.
     */
    public function viewHistory(User $user, Dossier $dossier): bool
    {
        return $dossier->rememberAccess('history:'.$user->id, fn () => $user->hasPermission('view_all_history')
            || Dossier::query()->whereKey($dossier->getKey())->historyVisibleTo($user)->exists());
    }

    /**
     * Page « Historique » du menu : ouverte dès qu'une permission d'historique est accordée.
     */
    public function viewAnyHistory(User $user): bool
    {
        return $user->hasPermission('view_all_history')
            || $user->hasPermission('view_own_history')
            || ($user->hasPermission('view_department_history') && $user->department_id !== null);
    }
}
