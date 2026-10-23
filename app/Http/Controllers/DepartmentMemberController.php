<?php

namespace App\Http\Controllers;

use App\Enums\RecipientStatus;
use App\Models\DossierRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Vue Responsable : employés de son département, sans données personnelles
 * inutiles (nom, prénom, fonction, statut du compte), avec leur charge de
 * traitement et les indicateurs de suivi du département.
 */
class DepartmentMemberController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('viewDepartmentMembers', User::class);

        $department = $request->user()->department;
        $open = [RecipientStatus::Received->value, RecipientStatus::InProgress->value];

        $members = User::query()
            ->select(['id', 'first_name', 'last_name', 'name', 'job_title', 'is_active', 'department_id', 'role_id', 'photo_path'])
            ->with('role:id,name,label')
            ->withCount([
                'assignedRecipients as open_count' => fn (Builder $query) => $query->whereIn('status', $open),
                'assignedRecipients as overdue_count' => fn (Builder $query) => $query->whereIn('status', $open)->where('due_at', '<', now()),
                'assignedRecipients as answered_count' => fn (Builder $query) => $query->where('status', RecipientStatus::Answered->value),
            ])
            ->where('department_id', $department?->id)
            ->orderByDesc('is_active')
            ->orderBy('last_name')
            ->paginate(25);

        $treatments = DossierRecipient::query()->where('department_id', $department?->id);
        $total = (clone $treatments)->count();
        $answered = (clone $treatments)->whereIn('status', [RecipientStatus::Answered->value, RecipientStatus::Archived->value])->count();
        $memberCounts = User::query()->where('department_id', $department?->id)
            ->selectRaw('COUNT(*) as total, SUM(is_active) as active')->first();

        $viewer = $request->user();
        $nextDue = (clone $treatments)->whereIn('status', $open)->where('due_at', '>=', now())->min('due_at');
        $nextDue = $nextDue ? Carbon::parse($nextDue)->locale('fr') : null;
        // Point d'attention : le membre actif (de la page) qui a le plus de traitements en retard.
        $attention = $members->getCollection()->where('is_active', true)->sortByDesc('overdue_count')->first(fn (User $member) => $member->overdue_count > 0);

        return Inertia::render('DepartmentMembers/Index', [
            'title' => 'Employés',
            'department' => [
                'name' => $department?->name ?? 'Département',
                'code' => $department?->code,
                'initials' => mb_strtoupper(mb_substr($department?->name ?? '?', 0, 2)),
            ],
            'managerName' => $department?->manager?->name,
            'summary' => [
                'members' => (int) $memberCounts->total,
                'active' => (int) $memberCounts->active,
                'open' => (clone $treatments)->whereIn('status', $open)->count(),
                'overdue' => (clone $treatments)->whereIn('status', $open)->where('due_at', '<', now())->count(),
                'answerRate' => $total > 0 ? (int) round($answered / $total * 100) : null,
                'averageHours' => (clone $treatments)->whereNotNull('answered_at')
                    ->value(DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, answered_at)) / 60, 1)')),
            ],
            'nextDue' => $nextDue ? [
                'short' => $nextDue->isToday() ? 'Aujourd’hui '.$nextDue->format('H:i') : $nextDue->isoFormat('ddd D MMM, HH:mm'),
                'long' => $nextDue->isoFormat('dddd D MMMM à HH:mm'),
            ] : null,
            'attention' => $attention ? ['name' => $attention->name, 'overdue' => $attention->overdue_count, 'open' => $attention->open_count] : null,
            // Nom, prénom, fonction, rôle et charge de traitement : aucune donnée personnelle inutile.
            'members' => $members->through(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'displayName' => trim($member->last_name.' '.$member->first_name),
                'jobTitle' => $member->job_title,
                'role' => $member->role?->label,
                'active' => (bool) $member->is_active,
                'open' => $member->open_count,
                'overdue' => $member->overdue_count,
                'answered' => $member->answered_count,
                'initials' => $member->initials(),
                'photoUrl' => $member->photo_path ? route('users.photo', $member) : null,
                'canDeactivate' => $viewer->can('deactivate', $member),
                'deactivateUrl' => route('users.deactivate', $member),
            ]),
            'urls' => [
                'dashboard' => route('dashboard'),
                'statistics' => Route::has('statistics.index') && ($viewer->hasPermission('view_department_statistics') || $viewer->hasPermission('view_global_statistics'))
                    ? route('statistics.index') : null,
            ],
        ]);
    }
}
