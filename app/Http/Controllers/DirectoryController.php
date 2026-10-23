<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Models\User;
use App\Support\DossierListItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Annuaire pour trouver un destinataire : département → nom et fonction.
 * Aucune donnée personnelle (téléphone, e-mail) n'est exposée.
 */
class DirectoryController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('viewDirectory', Department::class);

        // Dossiers envoyés par l'utilisateur, comptés une fois par département destinataire.
        $sentCounts = DossierRecipient::query()
            ->whereHas('dossier', fn (Builder $dossier) => $dossier->sentBy($request->user()))
            ->selectRaw('department_id, COUNT(DISTINCT dossier_id) as total')
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        return Inertia::render('Directory/Index', [
            'title' => 'Départements',
            'departments' => Department::query()
                ->active()
                ->orderBy('sort_order')
                ->with([
                    'manager:id',
                    'users' => fn ($users) => $users->active()
                        ->select(['id', 'department_id', 'first_name', 'last_name', 'name', 'job_title', 'photo_path'])
                        ->orderBy('last_name'),
                ])
                ->get(['id', 'name', 'code', 'manager_id'])
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'managerName' => $department->users->firstWhere('id', $department->manager_id)?->name,
                    'sent' => (int) ($sentCounts[$department->id] ?? 0),
                    'dossiersUrl' => route('directory.dossiers', $department),
                    // Nom, fonction et photo uniquement : aucune donnée personnelle (téléphone, e-mail).
                    'members' => $department->users->map(fn (User $member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'jobTitle' => $member->job_title,
                        'initials' => $member->initials(),
                        'photoUrl' => $member->photo_path ? route('users.photo', $member) : null,
                        'isManager' => $member->id === $department->manager_id,
                    ])->values(),
                ])
                ->values(),
        ]);
    }

    /**
     * Dossiers que l'utilisateur a envoyés à un département.
     */
    public function dossiers(Request $request, Department $department): InertiaResponse
    {
        Gate::authorize('viewDirectory', Department::class);

        $dossiers = Dossier::query()
            ->sentBy($request->user())
            ->whereHas('recipients', fn (Builder $recipient) => $recipient->where('department_id', $department->id))
            ->with(['requestType', 'creator', 'externalRequester', 'recipients.assignedUser'])
            ->latest('id')
            ->paginate(15);

        $user = $request->user();

        return Inertia::render('Directory/Dossiers', [
            'title' => 'Dossiers envoyés — '.$department->name,
            'department' => ['id' => $department->id, 'name' => $department->name],
            'dossiers' => $dossiers->through(fn (Dossier $dossier) => DossierListItem::for($dossier, $user)),
            'urls' => [
                // Responsables et RH : page « Départements » de l'espace Responsable ; sinon l'annuaire.
                'back' => $user->can('viewAny', Department::class) ? route('admin.departments.index') : route('directory.index'),
            ],
        ]);
    }

    /**
     * Personnel actif d'un département (JSON) : nom, prénom et fonction
     * uniquement, le responsable en premier.
     */
    public function members(Department $department): JsonResponse
    {
        Gate::authorize('viewDirectory', Department::class);

        abort_unless($department->is_active, 404);

        $members = $department->users()
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'job_title'])
            ->sortByDesc(fn (User $member) => $member->id === $department->manager_id)
            ->map(fn (User $member) => [
                'id' => $member->id,
                'last_name' => $member->last_name,
                'first_name' => $member->first_name,
                'job_title' => $member->job_title,
                'is_manager' => $member->id === $department->manager_id,
            ])
            ->values();

        return response()->json(['members' => $members]);
    }
}
