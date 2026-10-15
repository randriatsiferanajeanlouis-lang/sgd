<?php

namespace App\Http\Controllers;

use App\Enums\RecipientStatus;
use App\Models\DossierRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Vue Responsable : employés de son département, sans données personnelles
 * inutiles (nom, prénom, fonction, statut du compte), avec leur charge de
 * traitement et les indicateurs de suivi du département.
 */
class DepartmentMemberController extends Controller
{
    public function index(Request $request): View
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

        return view('department-members.index', [
            'department' => $department,
            'manager' => $department?->manager,
            'members' => $members,
            'summary' => [
                'members' => (int) $memberCounts->total,
                'active' => (int) $memberCounts->active,
                'open' => (clone $treatments)->whereIn('status', $open)->count(),
                'overdue' => (clone $treatments)->whereIn('status', $open)->where('due_at', '<', now())->count(),
                'next_due' => (clone $treatments)->whereIn('status', $open)->where('due_at', '>=', now())->min('due_at'),
                'answer_rate' => $total > 0 ? (int) round($answered / $total * 100) : null,
                'average_hours' => (clone $treatments)->whereNotNull('answered_at')
                    ->value(DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, answered_at)) / 60, 1)')),
            ],
        ]);
    }
}
