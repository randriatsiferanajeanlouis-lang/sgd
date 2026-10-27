<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\DossierActivityLog;
use App\Services\HistoryExporter;
use App\Support\Search;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exports A4 de l'historique, soumis au même droit que sa consultation.
 */
class HistoryController extends Controller
{
    private const DELETION_ACTIONS = ['dossier.trashed', 'dossier.purged_by_sender'];

    public function __construct(private readonly HistoryExporter $exporter) {}

    /**
     * Dernières actions sur tous les dossiers dont l'utilisateur peut lire l'historique.
     */
    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('viewAnyHistory', Dossier::class);

        $user = $request->user();
        $search = trim((string) $request->string('search'));
        $filters = $request->validate([
            'user' => ['nullable', 'integer'],
            'role' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', 'in:deletion'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = DossierActivityLog::query()
            ->whereHas('dossier', fn (Builder $dossier) => $dossier
                ->historyVisibleTo($user)
                ->when($search !== '', fn (Builder $matching) => $matching->where(fn (Builder $term) => $term
                    ->where('reference', 'like', Search::pattern($search))
                    ->orWhere('subject', 'like', Search::pattern($search)))))
            ->when($filters['user'] ?? null, fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->where(fn (Builder $any) => $any
                ->where('properties->actor_role', $role)
                ->orWhere(fn (Builder $legacy) => $legacy->whereNull('properties->actor_role')->whereHas('user.role', fn (Builder $r) => $r->where('name', $role)))))
            ->when($filters['kind'] ?? null, fn (Builder $query) => $query->whereIn('action', self::DELETION_ACTIONS))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', CarbonImmutable::parse($to)->endOfDay()))
            ->with(['user', 'subject', 'dossier:id,reference,subject'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('History/Index', [
            'title' => 'Historique',
            'search' => $search,
            'filters' => [
                'user' => isset($filters['user']) ? (string) $filters['user'] : '',
                'role' => $filters['role'] ?? '',
                'kind' => $filters['kind'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'users' => ! $user->hasPermission('view_all_history') ? [] : User::query()->whereIn('id', DossierActivityLog::query()->whereNotNull('user_id')->select('user_id'))
                ->orderBy('name')->get(['id', 'name'])->map(fn (User $u) => ['id' => (string) $u->id, 'name' => $u->name]),
            'roles' => $user->hasPermission('view_all_history') ? Role::query()->orderBy('name')->pluck('name') : [],
            'logs' => $logs->through(fn (DossierActivityLog $log) => [
                'id' => $log->id,
                'dossier' => [
                    'reference' => $log->dossier->reference,
                    'subject' => $log->dossier->subject,
                    'url' => route('dossiers.show', $log->dossier).'#historique',
                ],
                ...$this->exporter->row($log, $user),
            ]),
            'urls' => ['index' => route('history.index')],
        ]);
    }

    public function pdf(Request $request, Dossier $dossier): Response
    {
        Gate::authorize('viewHistory', $dossier);

        return $this->exporter->pdf($dossier, $request->user());
    }

    public function word(Request $request, Dossier $dossier): BinaryFileResponse
    {
        Gate::authorize('viewHistory', $dossier);

        return response()
            ->download($this->exporter->word($dossier, $request->user()), $this->exporter->filename($dossier, 'docx'), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend();
    }
}
