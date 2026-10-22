<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ActivityFeed;
use App\Support\Search;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Journal d'activité (page React) : connexions et actions d'administration.
 * Lecture seule : le journal est en ajout seul (triggers en base).
 */
class ActivityLogController extends Controller
{
    public function index(Request $request, ActivityFeed $feed): Response
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:50'],
            'actor' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $prefixes = ActivityFeed::categoryFilters()[$filters['category'] ?? ''] ?? null;

        $logs = AuditLog::query()
            ->with(['actor', 'target', 'department'])
            ->when($prefixes, fn (Builder $query) => $query->where(fn (Builder $any) => collect($prefixes)
                ->each(fn (string $prefix) => $any->orWhere('action', 'like', $prefix.'.%'))))
            ->when($filters['actor'] ?? null, fn (Builder $query, int $actor) => $query->where('actor_id', $actor))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', CarbonImmutable::parse($to)->endOfDay()))
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->where(fn (Builder $term) => $term
                ->where('action', 'like', Search::pattern($filters['search']))
                ->orWhere('comment', 'like', Search::pattern($filters['search']))
                ->orWhere('ip_address', 'like', Search::pattern($filters['search']))
                ->orWhereHas('actor', fn (Builder $actor) => $actor->where('name', 'like', Search::pattern($filters['search'])))))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log) => $feed->auditRow($log));

        $today = CarbonImmutable::today();
        $since = $today->subDays(13);
        $daily = AuditLog::query()->where('created_at', '>=', $since)->pluck('created_at')
            ->countBy(fn ($date) => $date->toDateString());

        return Inertia::render('Admin/ActivityLog', [
            'title' => 'Journal d’activité',
            'logs' => $logs,
            'filters' => [
                'category' => $filters['category'] ?? '',
                'actor' => isset($filters['actor']) ? (string) $filters['actor'] : '',
                'search' => $filters['search'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'categories' => array_keys(ActivityFeed::categoryFilters()),
            'actors' => User::query()->whereIn('id', AuditLog::query()->whereNotNull('actor_id')->distinct()->select('actor_id'))
                ->orderBy('last_name')->get(['id', 'name'])->map(fn (User $user) => ['id' => (string) $user->id, 'name' => $user->name]),
            'summary' => [
                'today' => AuditLog::query()->where('created_at', '>=', $today)->count(),
                'logins' => AuditLog::query()->where('action', 'auth.login')->where('created_at', '>=', $today)->count(),
                'failed' => AuditLog::query()->where('action', 'auth.failed')->where('created_at', '>=', $today->subDays(6))->count(),
                'week' => AuditLog::query()->where('created_at', '>=', $today->subDays(6))->count(),
                'daily' => collect(CarbonPeriod::create($since, $today))->map(fn ($day) => [
                    'day' => CarbonImmutable::parse($day)->locale('fr')->isoFormat('D MMM'),
                    'actions' => $daily[CarbonImmutable::parse($day)->toDateString()] ?? 0,
                ])->values(),
            ],
            'indexUrl' => route('admin.activity.index'),
        ]);
    }
}
