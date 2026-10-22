<?php

namespace App\Http\Controllers;

use App\Enums\DossierOrigin;
use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Models\Department;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Models\RequestType;
use App\Models\UrgencyLevel;
use App\Services\DossierTrends;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Statistiques du département (Responsable) ou générales (Administrateur).
 * Page React : resources/js/pages/Statistics.jsx.
 */
class StatisticsController extends Controller
{
    public function __invoke(Request $request, DossierTrends $trends): Response
    {
        $user = $request->user();
        $global = $user->hasPermission('view_global_statistics');

        abort_unless($global || ($user->hasPermission('view_department_statistics') && $user->department_id), 403);

        $departmentId = $global ? null : $user->department_id;

        $dossiers = Dossier::query()->when($departmentId, fn (Builder $query) => $query->concerningDepartment($departmentId));
        $treatments = DossierRecipient::query()->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId));
        $open = [RecipientStatus::Received->value, RecipientStatus::InProgress->value];

        $answered = (clone $treatments)->whereNotNull('answered_at');
        $answeredCount = (clone $answered)->count();
        $onTime = (clone $answered)->where(fn (Builder $query) => $query->whereNull('due_at')->orWhereColumn('answered_at', '<=', 'due_at'))->count();

        $treatmentRows = [
            ['label' => 'Ouverts', 'value' => (clone $treatments)->whereIn('status', $open)->count(), 'color' => 'var(--chart-1)'],
            ['label' => 'En retard', 'value' => (clone $treatments)->whereIn('status', $open)->where('due_at', '<', now())->count(), 'color' => 'var(--chart-2)'],
            ['label' => 'Répondus', 'value' => (clone $treatments)->where('status', RecipientStatus::Answered->value)->count(), 'color' => 'var(--chart-4)'],
            ['label' => 'Archivés', 'value' => (clone $treatments)->where('status', RecipientStatus::Archived->value)->count(), 'color' => 'var(--chart-5)'],
        ];

        return Inertia::render('Statistics', [
            'title' => 'Statistiques',
            'scopeLabel' => $global ? 'Tous les départements' : $user->department->name,
            'kpis' => [
                'dossiers' => (clone $dossiers)->count(),
                'open' => $treatmentRows[0]['value'],
                'overdue' => $treatmentRows[1]['value'],
                'averageResponseHours' => (clone $answered)->value(DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, answered_at)) / 60, 1)')),
                'onTimeRate' => $answeredCount > 0 ? (int) round($onTime / $answeredCount * 100) : null,
                'answered' => $answeredCount,
            ],
            'trends' => $trends->for($user),
            'monthly' => $this->monthly(clone $dossiers),
            'dossierStatuses' => collect(DossierStatus::cases())->map(fn (DossierStatus $status) => [
                'label' => $status->label(),
                'value' => (clone $dossiers)->where('status', $status->value)->count(),
            ]),
            'origins' => [
                ['label' => 'Internes', 'value' => (clone $dossiers)->where('origin', DossierOrigin::Internal->value)->count(), 'color' => 'var(--chart-1)'],
                ['label' => 'Externes', 'value' => (clone $dossiers)->where('origin', DossierOrigin::External->value)->count(), 'color' => 'var(--chart-5)'],
            ],
            'treatments' => $treatmentRows,
            'requestTypes' => RequestType::query()->get(['id', 'name'])
                ->map(fn (RequestType $type) => ['label' => $type->name, 'value' => (clone $dossiers)->where('request_type_id', $type->id)->count()])
                ->filter(fn (array $row) => $row['value'] > 0)->sortByDesc('value')->take(8)->values(),
            'urgencies' => UrgencyLevel::query()->orderBy('sort_order')->get(['id', 'name', 'color'])
                ->map(fn (UrgencyLevel $level) => ['label' => $level->name, 'value' => (clone $dossiers)->where('urgency_level_id', $level->id)->count(), 'color' => $level->color])
                ->push(['label' => 'Non précisée', 'value' => (clone $dossiers)->whereNull('urgency_level_id')->count(), 'color' => '#94a3b8'])
                ->filter(fn (array $row) => $row['value'] > 0)->values(),
            'departments' => $global ? Department::query()->orderBy('sort_order')->get()->map(function (Department $department) use ($open) {
                $recipients = DossierRecipient::query()->where('department_id', $department->id);

                return [
                    'name' => $department->name,
                    'code' => $department->code,
                    'color' => DossierTrends::COLORS[$department->code] ?? DossierTrends::FALLBACK,
                    'dossiers' => Dossier::query()->concerningDepartment($department->id)->count(),
                    'open' => (clone $recipients)->whereIn('status', $open)->count(),
                    'overdue' => (clone $recipients)->whereIn('status', $open)->where('due_at', '<', now())->count(),
                    'averageHours' => (float) ((clone $recipients)->whereNotNull('answered_at')
                        ->value(DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, answered_at)) / 60, 1)')) ?? 0),
                ];
            })->values() : [],
        ]);
    }

    /**
     * Volume mensuel sur 12 mois, demandes internes et externes.
     *
     * @return list<array{month: string, internal: int, external: int}>
     */
    private function monthly(Builder $dossiers): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(11);
        $rows = $dossiers->where('created_at', '>=', $start)->get(['created_at', 'origin'])
            ->groupBy(fn (Dossier $dossier) => $dossier->created_at->format('Y-m'));

        return collect(range(0, 11))->map(function (int $offset) use ($start, $rows) {
            $month = $start->addMonths($offset);
            $items = $rows[$month->format('Y-m')] ?? collect();

            return [
                'month' => $month->locale('fr')->isoFormat('MMM YY'),
                'internal' => $items->where('origin', DossierOrigin::Internal)->count(),
                'external' => $items->where('origin', DossierOrigin::External)->count(),
            ];
        })->all();
    }
}
