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
            'bulkUrls' => ['restore' => route('trash.restore-many'), 'destroy' => route('trash.destroy-many')],
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

    public function restoreMany(Request $request, DossierTrash $trash): RedirectResponse
    {
        $dossiers = $this->selected($request, 'restore');

        $trash->restoreMany($dossiers);

        return redirect()->route('trash.index')->with('status', $dossiers->count().' dossier(s) restauré(s) dans vos demandes.');
    }

    public function destroyMany(Request $request, DossierTrash $trash): RedirectResponse
    {
        $dossiers = $this->selected($request, 'forceDelete');

        $trash->purgeMany($dossiers);

        return redirect()->route('trash.index')->with('status', $dossiers->count().' dossier(s) supprimé(s) définitivement de votre espace.');
    }

    /**
     * Dossiers cochés dans la corbeille, tous autorisés pour l'action ou aucun.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Dossier>
     */
    private function selected(Request $request, string $ability): \Illuminate\Database\Eloquent\Collection
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['integer', 'distinct']])['ids'];

        $dossiers = Dossier::query()->whereKey($ids)->get();
        abort_if($dossiers->count() !== count($ids), 404);
        $dossiers->each(fn (Dossier $dossier) => Gate::authorize($ability, $dossier));

        return $dossiers;
    }
}
