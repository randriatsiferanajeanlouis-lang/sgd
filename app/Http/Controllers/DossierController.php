<?php

namespace App\Http\Controllers;

use App\Enums\DossierOrigin;
use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Enums\RequestScope;
use App\Http\Requests\StoreInternalRequest;
use App\Http\Requests\UpdateDossierRequest;
use App\Models\Department;
use App\Models\Dossier;
use App\Models\RequestType;
use App\Models\UrgencyLevel;
use App\Models\User;
use App\Services\DossierEditor;
use App\Services\DossierTrash;
use App\Services\InternalRequestService;
use App\Services\TreatmentService;
use App\Support\Deadline;
use App\Support\DossierListItem;
use App\Support\DossierPage;
use App\Support\Search;
use App\Support\SubmissionGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DossierController extends Controller
{
    /**
     * Mes dossiers : reçus, envoyés, archivés ou tous.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        abort_unless($request->user()->hasPermission('view_own_dossiers'), 403);

        $user = $request->user();
        $box = in_array($request->query('box'), ['received', 'sent', 'archived', 'all'], true) ? $request->query('box') : 'received';

        $query = Dossier::query()->relatedTo($user)->with(['requestType', 'creator', 'externalRequester', 'recipients.assignedUser']);

        match ($box) {
            'received' => $query->whereHas('recipients', fn (Builder $recipient) => $recipient
                ->where('assigned_user_id', $user->id)
                ->where('status', '!=', RecipientStatus::Archived->value)),
            'sent' => $query->sentBy($user)->where('status', '!=', DossierStatus::Archived->value),
            'archived' => $query->where(fn (Builder $archived) => $archived
                ->whereHas('recipients', fn (Builder $recipient) => $recipient->where('assigned_user_id', $user->id)->where('status', RecipientStatus::Archived->value))
                ->orWhere(fn (Builder $sent) => $sent->sentBy($user)->where('status', DossierStatus::Archived->value))),
            default => null,
        };

        $departmentId = $request->integer('department') ?: null;

        $query = $this->applyFilters($query, $request)
            ->when($departmentId, fn (Builder $concerning) => $concerning->concerningDepartment($departmentId))
            ->latest('id');

        if (Search::wantsSuggestions()) {
            return $this->suggestions($query);
        }

        // Cartes de synthèse : tous les dossiers de l'utilisateur, puis par département
        // concerné (émetteur ou destinataire) ; un dossier peut concerner plusieurs départements.
        $mine = fn () => Dossier::query()->relatedTo($user);

        return Inertia::render('Dossiers/Index', [
            'title' => 'Mes dossiers',
            'box' => $box,
            'selectedDepartment' => $departmentId,
            'filters' => ['search' => $request->string('search')->toString(), 'status' => $request->string('status')->toString()],
            'statuses' => collect(DossierStatus::cases())->map(fn (DossierStatus $status) => ['value' => $status->value, 'label' => $status->label()])->values(),
            'grandTotal' => $mine()->count(),
            'departmentTotals' => Department::query()->active()->orderBy('sort_order')->get(['id', 'name', 'code'])
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'total' => $mine()->concerningDepartment($department->id)->count(),
                ])->values(),
            'dossiers' => $query->paginate(15)->withQueryString()
                ->through(fn (Dossier $dossier) => DossierListItem::for($dossier, $user)),
            'urls' => ['index' => route('dossiers.index')],
        ]);
    }

    /**
     * Demande : les demandes internes envoyées par l'utilisateur (hors corbeille).
     */
    public function requests(Request $request): InertiaResponse|JsonResponse
    {
        Gate::authorize('createInternal', Dossier::class);

        if (Search::wantsSuggestions()) {
            return $this->suggestions($this->requestsQuery($request)->with(['requestType', 'creator', 'externalRequester'])->latest('id'));
        }

        [$sort, $direction, $grouped] = $this->requestsSorting($request);
        $statusOrder = collect(DossierStatus::cases())->map(fn (DossierStatus $status) => "'{$status->value}'")->implode(',');

        $dossiers = $this->requestsQuery($request)
            ->with(['requestType', 'recipients.assignedUser', 'latestActivity.user'])
            ->withCount(['documents' => fn (Builder $documents) => $documents->active()])
            ->when($grouped, fn (Builder $query) => $query->orderByRaw("FIELD(status, {$statusOrder})"))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Dossier $dossier) => $this->requestRow($dossier, $request->user()));

        $counts = Dossier::query()->sentBy($request->user())->where('origin', DossierOrigin::Internal->value)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Requests/Index', [
            'title' => 'Mes demandes',
            'dossiers' => $dossiers,
            'counts' => collect(DossierStatus::cases())->mapWithKeys(fn (DossierStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)]),
            'statuses' => collect(DossierStatus::cases())->map(fn (DossierStatus $status) => ['value' => $status->value, 'label' => $status->label(), 'color' => $status->color()]),
            'filters' => [
                'search' => (string) $request->string('search'),
                'status' => (string) $request->string('status'),
                'sort' => $sort,
                'dir' => $direction,
                'group' => $grouped,
            ],
            'urls' => [
                'index' => route('requests.index'),
                'create' => route('dossiers.create'),
                'createExternal' => $request->user()->can('createExternal', Dossier::class) ? route('external-requests.create') : null,
                'dossiers' => $request->user()->hasPermission('view_own_dossiers') ? route('dossiers.index') : null,
                'export' => route('requests.export'),
            ],
        ]);
    }

    /**
     * Export CSV (Excel) des demandes, avec les mêmes filtres que la liste.
     */
    public function exportRequests(Request $request): StreamedResponse
    {
        Gate::authorize('createInternal', Dossier::class);

        [$sort, $direction] = $this->requestsSorting($request);
        $rows = $this->requestsQuery($request)->with(['requestType', 'recipients.assignedUser'])->orderBy($sort, $direction)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : accents corrects dans Excel
            fputcsv($out, ['Référence', 'Objet', 'Type', 'Statut', 'Destinataires', 'Créée le', 'Échéance'], ';');
            foreach ($rows as $dossier) {
                fputcsv($out, [
                    $dossier->reference,
                    $dossier->subject,
                    $dossier->typeLabel(),
                    $dossier->status->label(),
                    $dossier->recipients->map(fn ($recipient) => $recipient->assignedUser->name.' ('.$recipient->status->label().')')->implode(', '),
                    $dossier->created_at->format('d/m/Y H:i'),
                    $dossier->due_at?->format('d/m/Y') ?? '',
                ], ';');
            }
            fclose($out);
        }, 'mes-demandes-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function requestsQuery(Request $request): Builder
    {
        return $this->applyFilters(
            Dossier::query()->sentBy($request->user())->where('origin', DossierOrigin::Internal->value),
            $request,
        );
    }

    /**
     * @return array{0: string, 1: string, 2: bool} colonne de tri, sens, regroupement par statut
     */
    private function requestsSorting(Request $request): array
    {
        $sort = in_array($request->query('sort'), ['reference', 'subject', 'created_at', 'updated_at', 'due_at'], true) ? $request->query('sort') : 'created_at';
        $direction = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        return [$sort, $direction, $request->query('group', '1') !== '0'];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestRow(Dossier $dossier, User $user): array
    {
        $closed = in_array($dossier->status, [DossierStatus::Answered, DossierStatus::Archived], true);
        $deadline = Deadline::describe($dossier->due_at, $closed);
        $changedAt = $dossier->latestActivity?->created_at ?? $dossier->updated_at;

        return [
            'id' => $dossier->id,
            'reference' => $dossier->reference,
            'subject' => $dossier->subject,
            'type' => $dossier->typeLabel(),
            'status' => ['value' => $dossier->status->value, 'label' => $dossier->status->label(), 'color' => $dossier->status->color()],
            'recipients' => $dossier->recipients->map(fn ($recipient) => [
                'name' => $recipient->assignedUser->name,
                'status' => $recipient->status->label(),
                'color' => $recipient->status->color(),
            ])->values(),
            'changedAt' => $changedAt->format('d/m/Y H:i'),
            'changedBy' => $dossier->latestActivity?->user?->name ?? $user->name,
            'createdAt' => $dossier->created_at->format('d/m/Y H:i'),
            'dueAt' => $dossier->due_at?->format('d/m/Y'),
            'deadline' => $deadline ? ['label' => $deadline['label'], 'overdue' => $deadline['overdue']] : null,
            'documents' => $dossier->documents_count,
            'showUrl' => route('dossiers.show', $dossier),
        ];
    }

    /**
     * « Supprimer » : le dossier part dans la corbeille de l'expéditeur.
     */
    public function destroy(Dossier $dossier, DossierTrash $trash): RedirectResponse
    {
        Gate::authorize('delete', $dossier);

        $trash->trash($dossier);

        return redirect()->route('requests.index')
            ->with('status', "Le dossier {$dossier->reference} a été placé dans la corbeille. Les destinataires le conservent.");
    }

    public function create(Request $request): InertiaResponse
    {
        Gate::authorize('createInternal', Dossier::class);

        return Inertia::render('Dossiers/Create', [
            'title' => 'Nouvelle demande',
            'requestTypes' => RequestType::query()->active()->availableFor(RequestScope::Internal)->orderBy('sort_order')->get(['id', 'name'])
                ->map(fn (RequestType $type) => ['value' => (string) $type->id, 'label' => $type->name])->values(),
            'urgencyLevels' => UrgencyLevel::query()->active()->orderBy('sort_order')->get(['id', 'name', 'response_delay_hours']),
            'directory' => $this->recipientDirectory($request->user()->id),
            'completeTypes' => RequestType::completeFormIds(),
            'otherTypeId' => RequestType::otherId(),
            'today' => now()->toDateString(),
            // Jeton à usage unique : le serveur refuse un second envoi du même formulaire.
            'submission' => (string) Str::uuid(),
            'urls' => [
                'store' => route('dossiers.store'),
                'back' => route('requests.index'),
            ],
        ]);
    }

    public function store(StoreInternalRequest $request, InternalRequestService $service): RedirectResponse
    {
        if (SubmissionGuard::alreadySubmitted($request)) {
            return redirect()->route('requests.index')->with('warning', 'Cette demande a déjà été envoyée : le second envoi a été ignoré.');
        }

        $dossier = $service->create($request->user(), $request->safe()->except('attachments'), $request->file('attachments', []));

        return redirect()->route('dossiers.show', $dossier)->with('status', "Le dossier {$dossier->reference} a été envoyé.");
    }

    public function edit(Dossier $dossier): InertiaResponse
    {
        Gate::authorize('update', $dossier);

        $scope = $dossier->isExternal() ? RequestScope::External : RequestScope::Internal;
        $options = fn ($models) => $models->map(fn ($model) => ['value' => (string) $model->id, 'label' => $model->name])->values();

        return Inertia::render('Dossiers/Edit', [
            'title' => 'Modifier '.$dossier->reference,
            'dossier' => [
                'id' => $dossier->id,
                'reference' => $dossier->reference,
                'external' => $dossier->isExternal(),
                'subject' => $dossier->subject,
                'request_type_id' => $dossier->request_type_id,
                'request_type_other' => $dossier->request_type_other,
                'urgency_level_id' => $dossier->urgency_level_id,
                'due_at' => $dossier->due_at?->toDateString(),
                'dueLabel' => $dossier->due_at?->format('d/m/Y H:i'),
                'description' => $dossier->description,
            ],
            'requestTypes' => $options(RequestType::query()->active()->availableFor($scope)->orderBy('sort_order')->get(['id', 'name'])),
            'urgencyLevels' => $options(UrgencyLevel::query()->active()->orderBy('sort_order')->get(['id', 'name'])),
            'otherTypeId' => RequestType::otherId(),
            'today' => now()->toDateString(),
            'urls' => [
                'update' => route('dossiers.update', $dossier),
                'cancel' => route('dossiers.show', $dossier),
            ],
        ]);
    }

    public function update(UpdateDossierRequest $request, Dossier $dossier, DossierEditor $editor): RedirectResponse
    {
        $editor->update($dossier, $request->user(), $request->safe()->except('comment'), $request->validated('comment'));

        return redirect()->route('dossiers.show', $dossier)->with('status', 'Le dossier a été modifié. Les modifications sont tracées dans l’historique.');
    }

    /**
     * À l'ouverture par un destinataire, son traitement passe de Reçu à En cours.
     */
    public function show(Request $request, Dossier $dossier, TreatmentService $treatments): InertiaResponse
    {
        Gate::authorize('view', $dossier);

        $user = $request->user();
        $myRecipient = $dossier->recipients()->where('assigned_user_id', $user->id)->first();

        if ($myRecipient) {
            $treatments->open($myRecipient);
            $myRecipient->refresh();
        }

        $dossier->load([
            'requestType', 'urgencyLevel', 'creator.department', 'originatingDepartment', 'externalRequester',
            'recipients' => fn ($recipients) => $recipients->with(['assignedUser', 'department'])->orderBy('id'),
            'validations' => fn ($validations) => $validations->with(['requester', 'validator'])->orderBy('id'),
            'responses' => fn ($responses) => $responses->with(['author.department', 'recipient', 'documents' => fn ($documents) => $documents->active()])->orderBy('id'),
            'documents' => fn ($documents) => $documents->with(['uploader', 'canceller'])->orderBy('id'),
        ]);

        $this->linkToDossier($dossier);
        $myRecipient?->setRelation('dossier', $dossier);

        $visibleResponses = $dossier->responses->filter(fn ($response) => $user->can('view', $response))->values();
        $documentGroups = $dossier->documents
            ->filter(fn ($document) => $document->response === null || $user->can('view', $document->response))
            ->groupBy('document_key')
            ->map(fn ($versions) => $versions->sortByDesc('version')->values());
        $forwardTargets = $myRecipient && $user->can('forward', $myRecipient)
            ? User::query()->active()->whereNotNull('department_id')->with('department')
                ->whereNotIn('id', $dossier->recipients->pluck('assigned_user_id')->push($user->id))
                ->orderBy('department_id')->orderBy('last_name')->get()
                ->mapWithKeys(fn (User $target) => [$target->id => $target->department?->name.' — '.$target->name.($target->job_title ? ' ('.$target->job_title.')' : '')])
            : collect();

        return Inertia::render('Dossiers/Show', DossierPage::props(
            $dossier, $user, $myRecipient, $visibleResponses, $documentGroups, $user->can('viewHistory', $dossier), $forwardTargets,
        ));
    }

    /**
     * Rattache réponses, pièces et destinataires à l'instance déjà chargée du
     * dossier : les contrôles d'accès (mémorisés sur cette instance) ne sont
     * ainsi calculés qu'une fois, au lieu d'une requête par élément affiché.
     */
    private function linkToDossier(Dossier $dossier): void
    {
        $responses = $dossier->responses->keyBy('id');

        foreach ($dossier->responses as $response) {
            $response->setRelation('dossier', $dossier);
            $response->documents->each(fn ($document) => $document->setRelation('dossier', $dossier)->setRelation('response', $response));
        }

        foreach ($dossier->documents as $document) {
            $document->setRelation('dossier', $dossier)->setRelation('response', $responses->get($document->response_id));
        }

        $dossier->recipients->each(fn ($recipient) => $recipient->setRelation('dossier', $dossier));
    }

    /**
     * Annuaire des destinataires possibles : département → utilisateurs actifs.
     *
     * @return list<array{id: int, name: string, users: list<array{id: int, name: string, job_title: ?string}>}>
     */
    private function recipientDirectory(int $excludedUserId): array
    {
        return Department::query()
            ->active()
            ->orderBy('sort_order')
            ->with(['users' => fn ($users) => $users->active()->where('id', '!=', $excludedUserId)->orderBy('last_name')])
            ->get()
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'users' => $department->users->map(fn ($member) => ['id' => $member->id, 'name' => $member->name, 'job_title' => $member->job_title])->all(),
            ])
            ->all();
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        $pattern = Search::pattern($request->string('search'));

        return $query
            ->when($request->filled('search'), fn (Builder $search) => $search->where(fn (Builder $term) => $term
                ->where('reference', 'like', $pattern)
                ->orWhere('subject', 'like', $pattern)
                ->orWhere('request_type_other', 'like', $pattern)
                // Expéditeur : auteur interne (nom, prénom, « prénom nom » ou « nom prénom »)…
                ->orWhereHas('creator', fn (Builder $creator) => $creator
                    ->where('name', 'like', $pattern)
                    ->orWhere('first_name', 'like', $pattern)
                    ->orWhere('last_name', 'like', $pattern)
                    ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) like ?", [$pattern])
                    ->orWhereRaw("CONCAT_WS(' ', last_name, first_name) like ?", [$pattern]))
                // … ou demandeur externe (nom ou organisme).
                ->orWhereHas('externalRequester', fn (Builder $requester) => $requester
                    ->where('full_name', 'like', $pattern)
                    ->orWhere('organization', 'like', $pattern))))
            ->when($request->filled('status'), fn (Builder $status) => $status->where('status', $request->string('status')));
    }

    /**
     * Propositions affichées sous la barre de recherche pendant la saisie.
     */
    private function suggestions(Builder $query): JsonResponse
    {
        return Search::suggestions($query, fn (Dossier $dossier) => [
            'label' => $dossier->reference.' — '.$dossier->shortSubject(),
            'detail' => collect([$dossier->typeLabel(), $dossier->isExternal() ? $dossier->externalRequester?->full_name : $dossier->creator?->name, $dossier->status->label()])->filter()->implode(' · '),
            'url' => route('dossiers.show', $dossier),
        ]);
    }
}
