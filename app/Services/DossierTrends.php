<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Courbes du tableau de bord : nombre de dossiers concernant chaque département
 * (émis par lui ou qui lui sont adressés), par jour ou par mois.
 *
 * Même périmètre que les statistiques : le responsable voit son département,
 * les statistiques générales couvrent tous les départements actifs.
 */
class DossierTrends
{
    /**
     * Couleur fixe par département (palette catégorielle validée, clair / sombre) :
     * une couleur suit toujours le même département, quel que soit le filtre.
     */
    public const COLORS = [
        'COLLECTE' => ['light' => '#2a78d6', 'dark' => '#3987e5'],
        'RSE' => ['light' => '#e34948', 'dark' => '#e66767'],
        'PRODUCTION' => ['light' => '#1baf7a', 'dark' => '#199e70'],
        'ARMEMENT' => ['light' => '#eda100', 'dark' => '#c98500'],
        'TECHNIQUE' => ['light' => '#4a3aa7', 'dark' => '#9085e9'],
        'ADMINISTRATION' => ['light' => '#e87ba4', 'dark' => '#d55181'],
    ];

    /** Département sans couleur attribuée (créé plus tard) : gris neutre. */
    public const FALLBACK = ['light' => '#6b7280', 'dark' => '#9ca3af'];

    /**
     * @return array{periods: array<string, array{label: string, labels: list<string>, series: list<array{name: string, color: array{light: string, dark: string}, data: list<int>, total: int}>}>}|null
     */
    public function for(User $user): ?array
    {
        $departments = $this->departmentsFor($user);

        if ($departments->isEmpty()) {
            return null;
        }

        $since = $this->oldest();

        return $this->build($departments->map(fn (Department $department) => [
            'name' => $department->name,
            'color' => self::COLORS[$department->code] ?? self::FALLBACK,
            'dates' => Dossier::query()->concerningDepartment($department->id)->where('created_at', '>=', $since)->pluck('created_at'),
        ])->all());
    }

    /**
     * Activité personnelle de n'importe quel utilisateur : dossiers reçus
     * (en tant que destinataire) et dossiers envoyés, sur les mêmes périodes.
     *
     * @return array{periods: array<string, array{label: string, labels: list<string>, series: list<array{name: string, color: array{light: string, dark: string}, data: list<int>, total: int}>}>}
     */
    public function personal(User $user): array
    {
        $since = $this->oldest();

        return $this->build([
            [
                'name' => 'Reçus',
                'color' => ['light' => '#1a1a8c', 'dark' => '#a5a5f5'],
                'dates' => DossierRecipient::query()->where('assigned_user_id', $user->id)->where('created_at', '>=', $since)->pluck('created_at'),
            ],
            [
                'name' => 'Envoyés',
                'color' => ['light' => '#eb6834', 'dark' => '#d95926'],
                'dates' => Dossier::query()->sentBy($user)->where('created_at', '>=', $since)->pluck('created_at'),
            ],
        ]);
    }

    /**
     * @return array<string, array{label: string, start: CarbonImmutable, unit: string, format: string}>
     */
    private function periods(): array
    {
        $today = CarbonImmutable::today();

        return [
            'week' => ['label' => 'Semaine', 'start' => $today->subDays(6), 'unit' => 'day', 'format' => 'ddd D'],
            'month' => ['label' => 'Mois', 'start' => $today->subDays(29), 'unit' => 'day', 'format' => 'D MMM'],
            'year' => ['label' => 'Année', 'start' => $today->startOfMonth()->subMonths(11), 'unit' => 'month', 'format' => 'MMM YY'],
        ];
    }

    private function oldest(): CarbonImmutable
    {
        return $this->periods()['year']['start'];
    }

    /**
     * Répartit les dates de chaque série par jour (semaine, mois) ou par mois (année).
     *
     * @param  list<array{name: string, color: array{light: string, dark: string}, dates: Collection<int, mixed>}>  $series
     */
    private function build(array $series): array
    {
        $today = CarbonImmutable::today();
        $series = array_map(fn (array $serie) => [...$serie, 'dates' => collect($serie['dates'])->map(fn ($date) => CarbonImmutable::parse($date))], $series);

        return ['periods' => collect($this->periods())->map(function (array $period) use ($series, $today) {
            $buckets = collect(CarbonPeriod::create($period['start'], '1 '.$period['unit'], $today))
                ->map(fn ($date) => CarbonImmutable::parse($date));
            $key = fn (CarbonImmutable $date) => $period['unit'] === 'day' ? $date->toDateString() : $date->format('Y-m');

            return [
                'label' => $period['label'],
                'labels' => $buckets->map(fn (CarbonImmutable $date) => $date->locale('fr')->isoFormat($period['format']))->values()->all(),
                'series' => array_map(function (array $serie) use ($buckets, $key, $period) {
                    $counts = $serie['dates']
                        ->filter(fn (CarbonImmutable $date) => $date->greaterThanOrEqualTo($period['start']))
                        ->countBy($key);
                    $data = $buckets->map(fn (CarbonImmutable $date) => (int) ($counts[$key($date)] ?? 0))->values()->all();

                    return ['name' => $serie['name'], 'color' => $serie['color'], 'data' => $data, 'total' => array_sum($data)];
                }, $series),
            ];
        })->all()];
    }

    /**
     * @return Collection<int, Department>
     */
    private function departmentsFor(User $user): Collection
    {
        if ($user->hasPermission('view_global_statistics')) {
            return Department::query()->active()->orderBy('sort_order')->get(['id', 'name', 'code']);
        }

        if ($user->hasPermission('view_department_statistics') && $user->department_id) {
            return Department::query()->whereKey($user->department_id)->get(['id', 'name', 'code']);
        }

        return collect();
    }
}
