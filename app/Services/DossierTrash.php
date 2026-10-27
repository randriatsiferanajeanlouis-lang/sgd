<?php

namespace App\Services;

use App\Models\Dossier;
use Illuminate\Support\Facades\DB;

/**
 * Corbeille de l'expéditeur. Aucune ligne n'est effacée : le dossier partagé,
 * ses pièces et son historique restent intacts pour les destinataires.
 * « Supprimer définitivement » le retire seulement, sans retour, de l'espace
 * de l'expéditeur.
 *
 * Le changement d'état et sa trace d'audit sont écrits dans la même
 * transaction : si la trace échoue, rien n'est supprimé. La ligne est
 * verrouillée puis relue, ce qui évite une trace en double en cas de double
 * clic ou de requêtes simultanées.
 */
class DossierTrash
{
    public function __construct(private readonly DossierHistory $history) {}

    public function trash(Dossier $dossier): void
    {
        DB::transaction(function () use ($dossier) {
            $fresh = $this->lock($dossier);

            if ($fresh->trashed_by_sender_at !== null) {
                return;
            }

            $dossier->forceFill(['trashed_by_sender_at' => now()])->save();

            $this->history->record($dossier, 'dossier.trashed', $dossier, properties: $this->trace($dossier));
        });
    }

    public function restore(Dossier $dossier): void
    {
        DB::transaction(function () use ($dossier) {
            $fresh = $this->lock($dossier);

            if ($fresh->trashed_by_sender_at === null || $fresh->purged_by_sender_at !== null) {
                return;
            }

            $dossier->forceFill(['trashed_by_sender_at' => null])->save();

            $this->history->record($dossier, 'dossier.restored', $dossier, properties: $this->trace($dossier));
        });
    }

    public function purge(Dossier $dossier): void
    {
        DB::transaction(function () use ($dossier) {
            $fresh = $this->lock($dossier);

            if ($fresh->purged_by_sender_at !== null) {
                return;
            }

            $dossier->forceFill(['purged_by_sender_at' => now()])->save();

            $this->history->record($dossier, 'dossier.purged_by_sender', $dossier, properties: $this->trace($dossier));
        });
    }

    private function lock(Dossier $dossier): Dossier
    {
        return Dossier::query()->whereKey($dossier->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function trace(Dossier $dossier): array
    {
        return [
            'reference' => $dossier->reference,
            'subject' => $dossier->subject,
            'result' => 'Réussi',
        ];
    }
}
