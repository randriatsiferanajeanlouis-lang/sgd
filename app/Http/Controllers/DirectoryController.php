<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Annuaire pour trouver un destinataire : département → nom et fonction.
 * Aucune donnée personnelle (téléphone, e-mail) n'est exposée.
 */
class DirectoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewDirectory', Department::class);

        return view('directory.index', [
            // Dossiers envoyés par l'utilisateur, comptés une fois par département destinataire.
            'sentCounts' => DossierRecipient::query()
                ->whereHas('dossier', fn (Builder $dossier) => $dossier->where('created_by', $request->user()->id))
                ->selectRaw('department_id, COUNT(DISTINCT dossier_id) as total')
                ->groupBy('department_id')
                ->pluck('total', 'department_id'),
            'departments' => Department::query()
                ->active()
                ->orderBy('sort_order')
                ->with([
                    'manager:id',
                    'users' => fn ($users) => $users->active()
                        ->select(['id', 'department_id', 'first_name', 'last_name', 'name', 'job_title', 'photo_path'])
                        ->orderBy('last_name'),
                ])
                ->get(['id', 'name', 'code', 'manager_id']),
        ]);
    }

    /**
     * Dossiers que l'utilisateur a envoyés à un département.
     */
    public function dossiers(Request $request, Department $department): View
    {
        Gate::authorize('viewDirectory', Department::class);

        $dossiers = Dossier::query()
            ->where('created_by', $request->user()->id)
            ->whereHas('recipients', fn (Builder $recipient) => $recipient->where('department_id', $department->id))
            ->with(['requestType', 'creator', 'externalRequester', 'recipients.assignedUser'])
            ->latest('id')
            ->paginate(15);

        return view('directory.dossiers', ['department' => $department, 'dossiers' => $dossiers]);
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
