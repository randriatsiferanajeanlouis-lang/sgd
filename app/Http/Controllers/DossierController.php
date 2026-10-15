<?php

namespace App\Http\Controllers;

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
use App\Services\InternalRequestService;
use App\Services\TreatmentService;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DossierController extends Controller
{
    /**
     * Mes dossiers : reçus, envoyés, archivés ou tous.
     */
    public function index(Request $request): View|JsonResponse
    {
        abort_unless($request->user()->hasPermission('view_own_dossiers'), 403);

        $user = $request->user();
        $box = in_array($request->query('box'), ['received', 'sent', 'archived', 'all'], true) ? $request->query('box') : 'received';

        $query = Dossier::query()->relatedTo($user)->with(['requestType', 'creator', 'externalRequester', 'recipients.assignedUser']);

        match ($box) {
            'received' => $query->whereHas('recipients', fn (Builder $recipient) => $recipient
                ->where('assigned_user_id', $user->id)
                ->where('status', '!=', RecipientStatus::Archived->value)),
            'sent' => $query->where('created_by', $user->id)->where('status', '!=', DossierStatus::Archived->value),
            'archived' => $query->where(fn (Builder $archived) => $archived
                ->whereHas('recipients', fn (Builder $recipient) => $recipient->where('assigned_user_id', $user->id)->where('status', RecipientStatus::Archived->value))
                ->orWhere(fn (Builder $sent) => $sent->where('created_by', $user->id)->where('status', DossierStatus::Archived->value))),
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

        return view('dossiers.index', [
            'box' => $box,
            'dossiers' => $query->paginate(15)->withQueryString(),
            'selectedDepartment' => $departmentId,
            'grandTotal' => $mine()->count(),
            'departmentTotals' => Department::query()->active()->orderBy('sort_order')->get(['id', 'name', 'code'])
                ->map(fn (Department $department) => [
                    'department' => $department,
                    'total' => $mine()->concerningDepartment($department->id)->count(),
                ]),
        ]);
    }

    /**
     * Dossiers du département (vue Responsable).
     */
    public function department(Request $request): View|JsonResponse
    {
        abort_unless($request->user()->hasPermission('view_department_dossiers'), 403);

        $query = Dossier::query()
            ->concerningDepartment($request->user()->department_id)
            ->with(['requestType', 'creator', 'externalRequester', 'recipients.assignedUser']);

        $query = $this->applyFilters($query, $request)->latest('id');

        if (Search::wantsSuggestions()) {
            return $this->suggestions($query);
        }

        return view('dossiers.department', [
            'department' => $request->user()->department,
            'dossiers' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('createInternal', Dossier::class);

        return view('dossiers.create', [
            'requestTypes' => RequestType::query()->active()->availableFor(RequestScope::Internal)->orderBy('sort_order')->pluck('name', 'id'),
            'urgencyLevels' => UrgencyLevel::query()->active()->orderBy('sort_order')->get(['id', 'name', 'response_delay_hours']),
            'directory' => $this->recipientDirectory($request->user()->id),
            'completeTypes' => RequestType::completeFormIds(),
        ]);
    }

    public function store(StoreInternalRequest $request, InternalRequestService $service): RedirectResponse
    {
        $dossier = $service->create($request->user(), $request->safe()->except('attachments'), $request->file('attachments', []));

        return redirect()->route('dossiers.show', $dossier)->with('status', "Le dossier {$dossier->reference} a été envoyé.");
    }

    public function edit(Dossier $dossier): View
    {
        Gate::authorize('update', $dossier);

        $scope = $dossier->isExternal() ? RequestScope::External : RequestScope::Internal;

        return view('dossiers.edit', [
            'dossier' => $dossier,
            'requestTypes' => RequestType::query()->active()->availableFor($scope)->orderBy('sort_order')->pluck('name', 'id'),
            'urgencyLevels' => UrgencyLevel::query()->active()->orderBy('sort_order')->pluck('name', 'id'),
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
    public function show(Request $request, Dossier $dossier, TreatmentService $treatments): View
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
            'responses' => fn ($responses) => $responses->with(['author', 'recipient', 'documents' => fn ($documents) => $documents->active()])->orderBy('id'),
            'documents' => fn ($documents) => $documents->with(['uploader', 'canceller'])->orderBy('id'),
        ]);

        $this->linkToDossier($dossier);
        $myRecipient?->setRelation('dossier', $dossier);

        return view('dossiers.show', [
            'dossier' => $dossier,
            'myRecipient' => $myRecipient,
            'visibleResponses' => $dossier->responses->filter(fn ($response) => $user->can('view', $response))->values(),
            'documentGroups' => $dossier->documents
                ->filter(fn ($document) => $document->response === null || $user->can('view', $document->response))
                ->groupBy('document_key')
                ->map(fn ($versions) => $versions->sortByDesc('version')->values()),
            'canViewHistory' => $user->can('viewHistory', $dossier),
            'forwardTargets' => $myRecipient && $user->can('forward', $myRecipient)
                ? User::query()->active()->whereNotNull('department_id')->with('department')
                    ->whereNotIn('id', $dossier->recipients->pluck('assigned_user_id')->push($user->id))
                    ->orderBy('department_id')->orderBy('last_name')->get()
                    ->mapWithKeys(fn (User $target) => [$target->id => $target->department?->name.' — '.$target->name.($target->job_title ? ' ('.$target->job_title.')' : '')])
                : collect(),
        ]);
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
            'label' => $dossier->reference.' — '.$dossier->subject,
            'detail' => collect([$dossier->requestType?->name, $dossier->isExternal() ? $dossier->externalRequester?->full_name : $dossier->creator?->name, $dossier->status->label()])->filter()->implode(' · '),
            'url' => route('dossiers.show', $dossier),
        ]);
    }
}
