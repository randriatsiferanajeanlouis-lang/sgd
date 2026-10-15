<?php

namespace App\Policies;

use App\Enums\DossierStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DocumentPolicy
{
    /**
     * Téléchargement normal : version active uniquement, permission
     * download_document, accès au dossier et, pour une pièce de réponse,
     * droit de lire cette réponse.
     */
    public function download(User $user, Document $document): bool
    {
        return $document->isActive() && $this->canReach($user, $document);
    }

    /**
     * Version remplacée ou annulée : consultable seulement par qui a accès à
     * l'historique du dossier (traçabilité), jamais proposée normalement.
     */
    public function downloadVersion(User $user, Document $document): bool
    {
        return ! $document->isActive()
            && $this->canReach($user, $document)
            && Gate::forUser($user)->allows('viewHistory', $document->dossier);
    }

    /**
     * Remplacer ou annuler : la personne qui a ajouté le document ou l'auteur
     * du dossier, tant que le dossier n'est pas archivé.
     */
    public function replace(User $user, Document $document): bool
    {
        return $document->isActive()
            && $document->dossier->status !== DossierStatus::Archived
            && in_array($user->id, [$document->uploaded_by, $document->dossier->created_by], true)
            && $this->canReach($user, $document);
    }

    public function cancel(User $user, Document $document): bool
    {
        return $this->replace($user, $document);
    }

    private function canReach(User $user, Document $document): bool
    {
        return $user->hasPermission('download_document')
            && Gate::forUser($user)->allows('view', $document->dossier)
            && ($document->response === null || Gate::forUser($user)->allows('view', $document->response));
    }
}
