<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DossierActivityLog;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UrgencyLevel;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Mise en forme du journal d'activité :
 *  - journal d'administration (audit_logs) : connexions, comptes, départements,
 *    rôles, paramètres, sauvegardes, corbeille ;
 *  - activité des dossiers (dossier_activity_logs), limitée aux dossiers dont
 *    l'utilisateur peut lire l'historique.
 */
class ActivityFeed
{
    /**
     * Catégories du journal d'administration : préfixe d'action => [libellé, couleur].
     */
    public const CATEGORIES = [
        'auth' => ['Connexions', 'sky'],
        'user' => ['Comptes', 'violet'],
        'department' => ['Départements', 'amber'],
        'role' => ['Rôles et permissions', 'rose'],
        'request_type' => ['Paramétrage', 'slate'],
        'urgency_level' => ['Paramétrage', 'slate'],
        'setting' => ['Paramétrage', 'slate'],
        'backup' => ['Sauvegardes', 'emerald'],
        'dossiers' => ['Dossiers', 'cyan'],
    ];

    public function __construct(private readonly HistoryExporter $history) {}

    /**
     * Filtre « catégorie » du journal : préfixes d'action regroupés par libellé.
     *
     * @return array<string, list<string>>
     */
    public static function categoryFilters(): array
    {
        return collect(self::CATEGORIES)
            ->map(fn (array $category, string $prefix) => ['label' => $category[0], 'prefix' => $prefix])
            ->groupBy('label')
            ->map(fn (Collection $rows) => $rows->pluck('prefix')->all())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function auditRow(AuditLog $log): array
    {
        $prefix = strtok($log->action, '.');
        [$category, $color] = self::CATEGORIES[$prefix] ?? ['Autre', 'slate'];
        $label = __('audit.'.$log->action);

        return [
            'id' => $log->id,
            'at' => $log->created_at->toIso8601String(),
            'date' => $log->created_at->format('d/m/Y'),
            'time' => $log->created_at->format('H:i'),
            'relative' => $log->created_at->locale('fr')->diffForHumans(options: CarbonInterface::JUST_NOW),
            'actor' => $log->actor ? ['name' => $log->actor->name, 'initials' => $log->actor->initials()] : null,
            'action' => $log->action,
            'label' => $label === 'audit.'.$log->action ? $log->action : $label,
            'category' => $category,
            'color' => $color,
            'target' => $this->targetLabel($log->target),
            'department' => $log->department?->name,
            'changes' => $this->changes($log->old_values ?? [], $log->new_values ?? []),
            'comment' => $log->comment,
            'ip' => $log->ip_address,
        ];
    }

    /**
     * Dernières actions sur les dossiers dont l'utilisateur peut lire l'historique.
     *
     * @return list<array<string, mixed>>
     */
    public function recentDossierActivity(User $user, int $limit = 6): array
    {
        return DossierActivityLog::query()
            ->whereHas('dossier', fn (Builder $dossier) => $dossier->historyVisibleTo($user))
            ->with(['user', 'subject', 'dossier:id,reference,subject'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (DossierActivityLog $log) => [
                'id' => 'd'.$log->id,
                'at' => $log->created_at->toIso8601String(),
                'relative' => $log->created_at->locale('fr')->diffForHumans(options: CarbonInterface::JUST_NOW),
                'title' => $this->history->row($log, $user)['action'],
                'subtitle' => $log->dossier->reference.' — '.$log->dossier->shortSubject(),
                'reference' => $log->dossier->reference,
                'subject' => $log->dossier->subject,
                'actor' => $log->user?->name ?? 'Système',
                'url' => route('dossiers.show', $log->dossier_id),
                'kind' => 'dossier',
            ])
            ->all();
    }

    /**
     * Dernières actions d'administration (journal d'activité).
     *
     * @return list<array<string, mixed>>
     */
    public function recentAudit(int $limit = 6): array
    {
        return AuditLog::query()->with(['actor', 'target', 'department'])->latest('id')->limit($limit)->get()
            ->map(function (AuditLog $log) {
                $row = $this->auditRow($log);

                return [
                    'id' => 'a'.$log->id,
                    'at' => $row['at'],
                    'relative' => $row['relative'],
                    'title' => $row['label'],
                    'subtitle' => $row['target'] ?? $row['category'],
                    'actor' => $row['actor']['name'] ?? 'Système',
                    'url' => route('admin.activity.index'),
                    'kind' => $row['category'],
                ];
            })
            ->all();
    }

    private function targetLabel(?Model $target): ?string
    {
        return match (true) {
            $target === null => null,
            $target instanceof User => $target->name,
            $target instanceof Department, $target instanceof RequestType, $target instanceof UrgencyLevel => $target->name,
            $target instanceof Role, $target instanceof Setting => $target->label,
            default => class_basename($target).' #'.$target->getKey(),
        };
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return list<array{field: string, old: ?string, new: ?string}>
     */
    private function changes(array $old, array $new): array
    {
        return collect(array_unique([...array_keys($old), ...array_keys($new)]))
            ->map(fn (string $field) => [
                'field' => $field,
                'old' => $this->scalar($old[$field] ?? null),
                'new' => $this->scalar($new[$field] ?? null),
            ])
            ->values()
            ->all();
    }

    private function scalar(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'oui' : 'non',
            is_array($value) => implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item), $value)),
            default => (string) $value,
        };
    }
}
