<?php

namespace App\Services;

use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Models\Dossier;
use Illuminate\Support\Collection;

/**
 * Statut global d'un dossier, déduit des statuts individuels sans jamais
 * les modifier. Pour une demande externe, « Répondu » et « Archivé » ne
 * sont posés que par le RH (réponse officielle, archivage).
 */
class DossierStatusSynchronizer
{
    public function __construct(private readonly DossierHistory $history) {}

    public function sync(Dossier $dossier): void
    {
        $statuses = $dossier->recipients()->pluck('status')->map(fn ($status) => $status instanceof RecipientStatus ? $status : RecipientStatus::from($status));

        $target = $dossier->isExternal()
            ? $this->externalStatus($dossier, $statuses)
            : $this->internalStatus($statuses);

        if ($target === $dossier->status) {
            return;
        }

        $previous = $dossier->status;
        $dossier->update([
            'status' => $target,
            'archived_at' => $target === DossierStatus::Archived ? now() : null,
        ]);

        $this->history->record($dossier, 'dossier.status_changed', $dossier, ['status' => $previous->label()], ['status' => $target->label()]);
    }

    /**
     * @param  Collection<int, RecipientStatus>  $statuses
     */
    private function internalStatus($statuses): DossierStatus
    {
        if ($statuses->isEmpty()) {
            return DossierStatus::Sent;
        }

        if ($statuses->every(fn (RecipientStatus $status) => $status === RecipientStatus::Archived)) {
            return DossierStatus::Archived;
        }

        if ($statuses->every(fn (RecipientStatus $status) => in_array($status, [RecipientStatus::Answered, RecipientStatus::Archived], true))) {
            return DossierStatus::Answered;
        }

        if ($statuses->contains(fn (RecipientStatus $status) => $status !== RecipientStatus::Received)) {
            return DossierStatus::InProgress;
        }

        return DossierStatus::Sent;
    }

    /**
     * @param  Collection<int, RecipientStatus>  $statuses
     */
    private function externalStatus(Dossier $dossier, $statuses): DossierStatus
    {
        if (in_array($dossier->status, [DossierStatus::Answered, DossierStatus::Archived], true)) {
            return $dossier->status;
        }

        return $statuses->contains(fn (RecipientStatus $status) => $status !== RecipientStatus::Received)
            ? DossierStatus::InProgress
            : DossierStatus::Sent;
    }
}
