<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Services\DossierTrash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Corbeille de l'expéditeur : restaurer ou supprimer définitivement de son espace.
 */
class DossierTrashController extends Controller
{
    /**
     * Page React (Inertia) : resources/js/pages/Dossiers/Trash.jsx.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewTrash', Dossier::class);

        $dossiers = Dossier::query()
            ->inSenderTrash($request->user())
            ->with(['requestType', 'recipients.assignedUser'])
            ->latest('trashed_by_sender_at')
            ->paginate(15)
            ->through(fn (Dossier $dossier) => [
                'id' => $dossier->id,
                'reference' => $dossier->reference,
                'subject' => $dossier->subject,
                'type' => $dossier->typeLabel(),
                'recipients' => $dossier->recipients->pluck('assignedUser.name')->implode(', '),
                'trashed_at' => $dossier->trashed_by_sender_at->format('d/m/Y H:i'),
                'restore_url' => route('trash.restore', $dossier),
                'destroy_url' => route('trash.destroy', $dossier),
            ]);

        return Inertia::render('Dossiers/Trash', [
            'title' => 'Corbeille',
            'dossiers' => $dossiers,
            'requestsUrl' => route('requests.index'),
        ]);
    }

    public function restore(Dossier $dossier, DossierTrash $trash): RedirectResponse
    {
        Gate::authorize('restore', $dossier);

        $trash->restore($dossier);

        return redirect()->route('trash.index')->with('status', "Le dossier {$dossier->reference} a été restauré dans vos demandes.");
    }

    public function destroy(Dossier $dossier, DossierTrash $trash): RedirectResponse
    {
        Gate::authorize('forceDelete', $dossier);

        $trash->purge($dossier);

        return redirect()->route('trash.index')->with('status', "Le dossier {$dossier->reference} a été supprimé définitivement de votre espace.");
    }
}
