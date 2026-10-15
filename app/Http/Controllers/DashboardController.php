<?php

namespace App\Http\Controllers;

use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Models\Department;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Models\Role;
use App\Services\DossierTrends;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DossierTrends $trends): View
    {
        $user = $request->user()->load(['department', 'role']);
        $openStatuses = [RecipientStatus::Received->value, RecipientStatus::InProgress->value];

        $myTreatments = DossierRecipient::query()->where('assigned_user_id', $user->id);

        $stats = [
            [
                'label' => 'Reçus à traiter',
                'value' => (clone $myTreatments)->whereIn('status', $openStatuses)->count(),
                'url' => route('dossiers.index', ['box' => 'received']),
            ],
            [
                'label' => 'Délais dépassés',
                'value' => (clone $myTreatments)->whereIn('status', $openStatuses)->where('due_at', '<', now())->count(),
                'url' => route('dossiers.index', ['box' => 'received']),
                'alert' => true,
            ],
            [
                'label' => 'Envoyés en cours',
                'value' => Dossier::query()->where('created_by', $user->id)->whereIn('status', [DossierStatus::Sent->value, DossierStatus::InProgress->value])->count(),
                'url' => route('dossiers.index', ['box' => 'sent']),
            ],
            [
                'label' => 'Répondus',
                'value' => (clone $myTreatments)->where('status', RecipientStatus::Answered->value)->count(),
                'url' => route('dossiers.index', ['box' => 'received']),
            ],
        ];

        $adminOverview = null;
        if ($user->role->name === Role::ADMIN) {
            $depts = Department::query()
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

            $adminOverview = [
                'departments' => $depts,
                'totals' => [
                    'users' => (int) $depts->sum('active_users_count'),
                    'open' => (int) $depts->sum('open_count'),
                    'overdue' => (int) $depts->sum('overdue_count'),
                    'answer_rate' => $depts->sum('total_count') > 0
                        ? (int) round($depts->sum('answered_count') / $depts->sum('total_count') * 100)
                        : null,
                ],
                'attention' => $depts->sortByDesc('overdue_count')->first(fn ($d) => $d->overdue_count > 0),
                'alert_count' => $depts->where('overdue_count', '>', 0)->count(),
            ];
        }

        return view('dashboard', [
            'user' => $user,
            'stats' => $stats,
            'trends' => $trends->for($user),
            'personalTrends' => $trends->personal($user),
            'adminOverview' => $adminOverview,
            'myTreatments' => collect(RecipientStatus::cases())->mapWithKeys(fn (RecipientStatus $status) => [
                $status->label() => (clone $myTreatments)->where('status', $status->value)->count(),
            ]),
        ]);
    }
}
