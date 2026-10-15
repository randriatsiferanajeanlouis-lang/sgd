<?php

namespace App\Http\Controllers;

use App\Enums\DossierOrigin;
use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Models\Department;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Services\DossierTrends;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Statistiques du département (Responsable) ou générales (Administrateur).
 */
class StatisticsController extends Controller
{
    public function __invoke(Request $request, DossierTrends $trends): View
    {
        $user = $request->user();
        $global = $user->hasPermission('view_global_statistics');

        abort_unless($global || ($user->hasPermission('view_department_statistics') && $user->department_id), 403);

        $departmentId = $global ? null : $user->department_id;

        $dossiers = Dossier::query()->when($departmentId, fn (Builder $query) => $query->concerningDepartment($departmentId));
        $treatments = DossierRecipient::query()->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId));
        $open = [RecipientStatus::Received->value, RecipientStatus::InProgress->value];

        return view('statistics.index', [
            'trends' => $trends->for($user),
            'scopeLabel' => $global ? 'Tous les départements' : $user->department->name,
            'dossierStatuses' => collect(DossierStatus::cases())->mapWithKeys(fn (DossierStatus $status) => [
                $status->label() => (clone $dossiers)->where('status', $status->value)->count(),
            ]),
            'origins' => [
                'Demandes internes' => (clone $dossiers)->where('origin', DossierOrigin::Internal->value)->count(),
                'Demandes externes' => (clone $dossiers)->where('origin', DossierOrigin::External->value)->count(),
            ],
            'treatments' => [
                'Traitements ouverts' => (clone $treatments)->whereIn('status', $open)->count(),
                'Délais dépassés' => (clone $treatments)->whereIn('status', $open)->where('due_at', '<', now())->count(),
                'Répondus' => (clone $treatments)->where('status', RecipientStatus::Answered->value)->count(),
                'Archivés' => (clone $treatments)->where('status', RecipientStatus::Archived->value)->count(),
            ],
            'averageResponseHours' => (clone $treatments)->whereNotNull('answered_at')
                ->value(DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, answered_at)) / 60, 1)')),
            'departments' => $global ? Department::query()->orderBy('sort_order')->get()->map(fn (Department $department) => [
                'name' => $department->name,
                'code' => $department->code,
                'dossiers' => Dossier::query()->concerningDepartment($department->id)->count(),
                'open' => DossierRecipient::query()->where('department_id', $department->id)->whereIn('status', $open)->count(),
                'overdue' => DossierRecipient::query()->where('department_id', $department->id)->whereIn('status', $open)->where('due_at', '<', now())->count(),
            ]) : collect(),
        ]);
    }
}
