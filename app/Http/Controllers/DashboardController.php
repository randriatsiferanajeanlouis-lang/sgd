<?php

namespace App\Http\Controllers;

use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityFeed;
use App\Services\DossierTrends;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableau de bord (page React resources/js/pages/Dashboard.jsx).
 */
class DashboardController extends Controller
{
    /** Nombre de mois des mini-courbes des tuiles. */
    private const SPARK_MONTHS = 6;

    public function __invoke(Request $request, DossierTrends $trends, ActivityFeed $feed): Response
    {
        $user = $request->user()->load(['department', 'role']);
        $openStatuses = [RecipientStatus::Received->value, RecipientStatus::InProgress->value];
        $myTreatments = DossierRecipient::query()->where('assigned_user_id', $user->id);
        $sent = Dossier::query()->sentBy($user);

        $stats = [
            $this->tile('received', 'Reçus à traiter', 'inbox', 'Dossiers à traiter',
                (clone $myTreatments)->whereIn('status', $openStatuses)->count(),
                (clone $myTreatments)->pluck('created_at'),
                route('dossiers.index', ['box' => 'received'])),
            $this->tile('overdue', 'Délais dépassés', 'clock', 'Échéance passée',
                (clone $myTreatments)->whereIn('status', $openStatuses)->where('due_at', '<', now())->count(),
                (clone $myTreatments)->whereNotNull('due_at')->where(fn (Builder $late) => $late
                    ->whereColumn('answered_at', '>', 'due_at')
                    ->orWhere(fn (Builder $open) => $open->whereIn('status', $openStatuses)->where('due_at', '<', now())))->pluck('due_at'),
                route('dossiers.index', ['box' => 'received'])),
            $this->tile('sent', 'Envoyés en cours', 'send', 'En attente de réponse',
                (clone $sent)->whereIn('status', [DossierStatus::Sent->value, DossierStatus::InProgress->value])->count(),
                (clone $sent)->pluck('created_at'),
                route('dossiers.index', ['box' => 'sent'])),
            $this->tile('answered', 'Répondus', 'check', 'Traitements répondus',
                (clone $myTreatments)->where('status', RecipientStatus::Answered->value)->count(),
                (clone $myTreatments)->whereNotNull('answered_at')->pluck('answered_at'),
                route('dossiers.index', ['box' => 'received'])),
        ];

        // Administrateur : pas de dossiers personnels (aucun département) → chiffres de toute l'organisation.
        $isAdmin = $user->role->name === Role::ADMIN;
        if ($isAdmin) {
            $allTreatments = DossierRecipient::query();
            $stats = [
                $this->tile('dossiers', 'Dossiers', 'folder', 'Tous départements', Dossier::query()->count(),
                    Dossier::query()->pluck('created_at'), route('dossiers.index', ['box' => 'all'])),
                $this->tile('open', 'Traitements ouverts', 'inbox', 'En attente de réponse', (clone $allTreatments)->whereIn('status', $openStatuses)->count(),
                    (clone $allTreatments)->pluck('created_at'), route('statistics.index')),
                $this->tile('overdue', 'Délais dépassés', 'clock', 'Échéance passée', (clone $allTreatments)->whereIn('status', $openStatuses)->where('due_at', '<', now())->count(),
                    (clone $allTreatments)->whereNotNull('due_at')->where(fn (Builder $late) => $late
                        ->whereColumn('answered_at', '>', 'due_at')
                        ->orWhere(fn (Builder $open) => $open->whereIn('status', $openStatuses)->where('due_at', '<', now())))->pluck('due_at'),
                    route('statistics.index')),
                $this->tile('answered', 'Réponses', 'check', 'Traitements répondus', (clone $allTreatments)->whereNotNull('answered_at')->count(),
                    (clone $allTreatments)->whereNotNull('answered_at')->pluck('answered_at'), route('statistics.index')),
            ];
        }

        $canSeeHistory = $user->can('viewAnyHistory', Dossier::class);
        $canSeeAudit = $user->hasPermission('view_activity_log');

        return Inertia::render('Dashboard', [
            'title' => 'Tableau de bord',
            'user' => [
                'firstName' => $user->first_name ?: $user->name,
                'name' => $user->name,
                'initials' => $user->initials(),
                'role' => $user->role->label,
                'department' => $user->department?->name,
            ],
            'isAdmin' => $isAdmin,
            'stats' => $stats,
            'personalTrends' => $isAdmin ? null : $trends->personal($user),
            'trends' => $trends->for($user),
            'myTreatments' => $isAdmin
                ? collect(DossierStatus::cases())->map(fn (DossierStatus $status) => [
                    'label' => $status->label(),
                    'value' => Dossier::query()->where('status', $status->value)->count(),
                ])
                : collect(RecipientStatus::cases())->map(fn (RecipientStatus $status) => [
                    'label' => $status->label(),
                    'value' => (clone $myTreatments)->where('status', $status->value)->count(),
                ]),
            'activity' => [
                'items' => $canSeeHistory ? $feed->recentDossierActivity($user) : ($canSeeAudit ? $feed->recentAudit() : []),
                'url' => $canSeeHistory ? route('history.index') : ($canSeeAudit ? route('admin.activity.index') : null),
            ],
            'overview' => $this->overview($user),
            'adminOverview' => $isAdmin ? $this->adminOverview($openStatuses) : null,
            'links' => [
                'statistics' => Route::has('statistics.index') && ($user->hasPermission('view_global_statistics') || $user->hasPermission('view_department_statistics')) ? route('statistics.index') : null,
            ],
        ]);
    }

    /**
     * Tuile chiffrée avec sa mini-courbe (événements par mois) et la variation du mois.
     *
     * @param  iterable<\DateTimeInterface|null>  $dates
     * @return array<string, mixed>
     */
    private function tile(string $key, string $label, string $icon, string $caption, int $value, iterable $dates, string $url): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(self::SPARK_MONTHS - 1);
        $counts = collect($dates)->filter()->map(fn ($date) => CarbonImmutable::parse($date))
            ->filter(fn (CarbonImmutable $date) => $date->greaterThanOrEqualTo($start) && $date->lessThanOrEqualTo(now()->endOfMonth()))
            ->countBy(fn (CarbonImmutable $date) => $date->format('Y-m'));

        $series = collect(range(0, self::SPARK_MONTHS - 1))->map(fn (int $offset) => [
            'month' => $start->addMonths($offset)->locale('fr')->isoFormat('MMM'),
            'value' => $counts[$start->addMonths($offset)->format('Y-m')] ?? 0,
        ]);

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'caption' => $caption,
            'value' => $value,
            'thisMonth' => $series->last()['value'],
            'spark' => $series->values(),
            'url' => $url,
        ];
    }

    /**
     * Encadré « Vue d'ensemble » : quelques compteurs utiles selon le rôle.
     *
     * @return list<array{label: string, value: int, icon: string, url: ?string}>
     */
    private function overview(User $user): array
    {
        if ($user->role->name === Role::ADMIN) {
            return [
                ['label' => 'Départements actifs', 'value' => Department::query()->active()->count(), 'icon' => 'building', 'url' => route('admin.departments.index')],
                ['label' => 'Utilisateurs actifs', 'value' => User::query()->active()->count(), 'icon' => 'users', 'url' => route('admin.users.index')],
                ['label' => 'Rôles', 'value' => Role::query()->count(), 'icon' => 'shield', 'url' => route('admin.roles.index')],
                ['label' => 'Types de demandes', 'value' => RequestType::query()->where('is_active', true)->count(), 'icon' => 'document', 'url' => route('admin.settings.index')],
                ['label' => 'Actions journalisées (7 j)', 'value' => AuditLog::query()->where('created_at', '>=', now()->subDays(6)->startOfDay())->count(), 'icon' => 'activity', 'url' => $user->hasPermission('view_activity_log') ? route('admin.activity.index') : null],
            ];
        }

        return array_values(array_filter([
            $user->can('createInternal', Dossier::class)
                ? ['label' => 'Mes demandes envoyées', 'value' => Dossier::query()->sentBy($user)->count(), 'icon' => 'send', 'url' => route('requests.index')] : null,
            ['label' => 'Dossiers reçus', 'value' => DossierRecipient::query()->where('assigned_user_id', $user->id)->count(), 'icon' => 'inbox', 'url' => route('dossiers.index', ['box' => 'received'])],
            ['label' => 'Archivés', 'value' => DossierRecipient::query()->where('assigned_user_id', $user->id)->where('status', RecipientStatus::Archived->value)->count(), 'icon' => 'folder', 'url' => route('dossiers.index', ['box' => 'archived'])],
            $user->can('viewTrash', Dossier::class)
                ? ['label' => 'Dans la corbeille', 'value' => Dossier::query()->inSenderTrash($user)->count(), 'icon' => 'trash', 'url' => route('trash.index')] : null,
            ['label' => 'Notifications non lues', 'value' => $user->unreadNotifications()->count(), 'icon' => 'bell', 'url' => route('notifications.index')],
        ]));
    }

    /**
     * Vue d'ensemble des départements (administrateur).
     *
     * @param  list<string>  $openStatuses
     * @return array<string, mixed>
     */
    private function adminOverview(array $openStatuses): array
    {
        $departments = Department::query()
            ->active()
            ->withCount([
                'users as users_count',
                'users as active_users_count' => fn (Builder $q) => $q->where('is_active', true),
                'dossierRecipients as open_count' => fn (Builder $q) => $q->whereIn('status', $openStatuses),
                'dossierRecipients as overdue_count' => fn (Builder $q) => $q->whereIn('status', $openStatuses)->where('due_at', '<', now()),
                'dossierRecipients as answered_count' => fn (Builder $q) => $q->whereIn('status', [RecipientStatus::Answered->value, RecipientStatus::Archived->value]),
                'dossierRecipients as total_count',
            ])
            ->with('manager:id,first_name,last_name,name')
            ->orderBy('sort_order')
            ->get();

        $total = (int) $departments->sum('total_count');
        $attention = $departments->sortByDesc('overdue_count')->first(fn (Department $department) => $department->overdue_count > 0);

        return [
            'departments' => $departments->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'manager' => $department->manager?->name,
                'users' => $department->users_count,
                'activeUsers' => $department->active_users_count,
                'open' => $department->open_count,
                'overdue' => $department->overdue_count,
                'url' => route('admin.users.index', ['department' => $department->id]),
            ])->values(),
            'totals' => [
                'users' => (int) $departments->sum('active_users_count'),
                'open' => (int) $departments->sum('open_count'),
                'overdue' => (int) $departments->sum('overdue_count'),
                'answerRate' => $total > 0 ? (int) round($departments->sum('answered_count') / $total * 100) : null,
            ],
            'attention' => $attention ? ['name' => $attention->name, 'overdue' => $attention->overdue_count, 'open' => $attention->open_count] : null,
            'links' => [
                'users' => route('admin.users.index'),
                'createUser' => route('admin.users.create'),
                'departments' => route('admin.departments.index'),
                'settings' => route('admin.settings.index'),
            ],
        ];
    }
}
