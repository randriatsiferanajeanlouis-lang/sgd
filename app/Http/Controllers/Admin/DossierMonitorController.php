<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DossierStatus;
use App\Http\Controllers\Controller;
use App\Models\Dossier;
use App\Support\DossierListItem;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Suivi de tous les dossiers qui circulent (administrateur, permission
 * view_all_dossiers) : lecture seule. Les dossiers retirés par leur
 * expéditeur (corbeille, suppression définitive) y restent visibles.
 */
class DossierMonitorController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        abort_unless($request->user()->hasPermission('view_all_dossiers'), 403);

        $search = trim((string) $request->string('search'));
        $removal = in_array($request->query('removal'), ['any', 'trashed', 'purged'], true) ? (string) $request->query('removal') : '';

        $dossiers = Dossier::query()
            ->with(['requestType', 'creator', 'externalRequester', 'recipients.assignedUser'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $term) => $term
                ->where('reference', 'like', Search::pattern($search))
                ->orWhere('subject', 'like', Search::pattern($search))
                ->orWhereHas('creator', fn (Builder $creator) => $creator->where('name', 'like', Search::pattern($search)))
                ->orWhereHas('externalRequester', fn (Builder $requester) => $requester->where('full_name', 'like', Search::pattern($search)))))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($removal === 'any', fn (Builder $query) => $query->whereNotNull('trashed_by_sender_at'))
            ->when($removal === 'trashed', fn (Builder $query) => $query->whereNotNull('trashed_by_sender_at')->whereNull('purged_by_sender_at'))
            ->when($removal === 'purged', fn (Builder $query) => $query->whereNotNull('purged_by_sender_at'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $user = $request->user();

        return Inertia::render('Admin/Dossiers/Index', [
            'title' => 'Suivi des dossiers',
            'filters' => ['search' => $search, 'status' => $request->string('status')->toString(), 'removal' => $removal],
            'statuses' => collect(DossierStatus::cases())->map(fn (DossierStatus $status) => ['value' => $status->value, 'label' => $status->label()])->values(),
            'counts' => [
                'all' => Dossier::query()->count(),
                'trashed' => Dossier::query()->whereNotNull('trashed_by_sender_at')->whereNull('purged_by_sender_at')->count(),
                'purged' => Dossier::query()->whereNotNull('purged_by_sender_at')->count(),
            ],
            'dossiers' => $dossiers->through(fn (Dossier $dossier) => DossierListItem::for($dossier, $user)),
            'urls' => ['index' => route('admin.dossiers.index')],
        ]);
    }
}
