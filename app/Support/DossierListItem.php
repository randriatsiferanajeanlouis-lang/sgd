<?php

namespace App\Support;

use App\Enums\DossierStatus;
use App\Models\Dossier;
use App\Models\User;

/**
 * Ligne de tableau de dossiers envoyée aux pages React (même contenu que le
 * composant Blade <x-dossier-table>) : seules les informations affichées,
 * calculées pour l'utilisateur qui consulte la liste.
 */
final class DossierListItem
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Dossier $dossier, User $viewer): array
    {
        $mine = $dossier->recipients->firstWhere('assigned_user_id', $viewer->id);
        $closed = $mine
            ? ! $mine->status->isOpen()
            : in_array($dossier->status, [DossierStatus::Answered, DossierStatus::Archived], true);
        $dueAt = $mine?->due_at ?? $dossier->due_at;

        return [
            'id' => $dossier->id,
            'reference' => $dossier->reference,
            'external' => $dossier->isExternal(),
            'subject' => $dossier->subject,
            'type' => $dossier->typeLabel(),
            'sender' => $dossier->isExternal() ? ($dossier->externalRequester?->full_name ?? '—') : $dossier->creator->name,
            'recipients' => $dossier->recipients->map(fn ($recipient) => [
                'name' => $recipient->assignedUser->name,
                'status' => $recipient->status->label(),
                'color' => $recipient->status->color(),
            ])->values()->all(),
            'status' => $mine
                ? ['label' => $mine->status->label(), 'color' => $mine->status->color(), 'mine' => true]
                : ['label' => $dossier->status->label(), 'color' => $dossier->status->color(), 'mine' => false],
            'deadline' => Deadline::describe($dueAt, $closed) ?? ($dueAt ? ['label' => $dueAt->format('d/m/Y'), 'color' => null, 'overdue' => false] : null),
            // Visible seulement par qui voit tous les dossiers (administrateur) : suivi des dossiers retirés par leur expéditeur.
            'removal' => $viewer->hasPermission('view_all_dossiers') ? $dossier->senderRemoval() : null,
            'showUrl' => route('dossiers.show', $dossier),
            'editUrl' => $viewer->can('update', $dossier) ? route('dossiers.edit', $dossier) : null,
            'deleteUrl' => $viewer->can('delete', $dossier) ? route('dossiers.destroy', $dossier) : null,
        ];
    }
}
