<?php

namespace App\Services;

use App\Models\Dossier;
use Illuminate\Support\Facades\DB;

/**
 * Corbeille de l'expéditeur. Aucune ligne n'est effacée : le dossier partagé,
 * ses pièces et son historique restent intacts pour les destinataires.
 * « Supprimer définitivement » le retire seulement, sans retour, de l'espace
 * de l'expéditeur.
 */
class DossierTrash
{
    public function __construct(private readonly DossierHistory $history) {}

    public function trash(Dossier $dossier): void
    {
        DB::transaction(function () use ($dossier) {
            $dossier->forceFill(['trashed_by_sender_at' => now()])->save();

            $this->history->record($dossier, 'dossier.trashed', $dossier);
        });
    }

    public function restore(Dossier $dossier): void
    {
        DB::transaction(function () use ($dossier) {
            $dossier->forceFill(['trashed_by_sender_at' => null])->save();

            $this->history->record($dossier, 'dossier.restored', $dossier);
        });
    }

    public function purge(Dossier $dossier): void
    {
        DB::transaction(function () use ($dossier) {
            $dossier->forceFill(['purged_by_sender_at' => now()])->save();

            $this->history->record($dossier, 'dossier.purged_by_sender', $dossier);
        });
    }
}
