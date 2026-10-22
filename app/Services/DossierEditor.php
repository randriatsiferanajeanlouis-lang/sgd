<?php

namespace App\Services;

use App\Models\Dossier;
use App\Models\RequestType;
use App\Models\UrgencyLevel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Modification d'un dossier par son auteur : chaque champ modifié est
 * historisé avec son ancienne et sa nouvelle valeur, et les destinataires
 * sont prévenus de la correction.
 */
class DossierEditor
{
    public function __construct(
        private readonly DossierHistory $history,
        private readonly DossierNotifier $notifier,
    ) {}

    /**
     * @param  array{subject: string, description?: string|null, request_type_id: int, request_type_other?: string|null, urgency_level_id?: int|null, due_at?: string|null}  $data
     */
    public function update(Dossier $dossier, User $editor, array $data, ?string $comment): void
    {
        DB::transaction(function () use ($dossier, $editor, $data, $comment) {
            $before = $this->readable($dossier);

            $dossier->fill([
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'request_type_id' => $data['request_type_id'],
                'request_type_other' => $data['request_type_other'] ?? null,
                'urgency_level_id' => $data['urgency_level_id'] ?? $dossier->urgency_level_id,
            ]);

            if (! $dossier->isExternal() && ! empty($data['due_at'])) {
                $dossier->due_at = Carbon::parse($data['due_at'])->endOfDay();
                $dossier->recipients()->whereIn('status', ['received', 'in_progress'])->update(['due_at' => $dossier->due_at]);
            }

            $dossier->save();

            $after = $this->readable($dossier->refresh());
            $changedKeys = array_keys(array_diff_assoc($after, $before));

            if ($changedKeys === []) {
                return;
            }

            $this->history->record(
                $dossier,
                'dossier.updated',
                $dossier,
                array_intersect_key($before, array_flip($changedKeys)),
                array_intersect_key($after, array_flip($changedKeys)),
                $comment,
            );

            $this->notifier->notify(
                $dossier->recipients()->with('assignedUser')->get()->pluck('assignedUser'),
                'dossier_updated',
                "{$editor->name} a modifié le dossier {$dossier->reference} (".implode(', ', $changedKeys).').',
                $dossier,
            );
        });
    }

    /**
     * Valeurs lisibles, utilisées telles quelles dans l'historique et les exports.
     *
     * @return array<string, string>
     */
    private function readable(Dossier $dossier): array
    {
        return [
            'objet' => (string) $dossier->subject,
            'description' => (string) $dossier->description,
            'type' => filled($dossier->request_type_other)
                ? $dossier->request_type_other
                : (string) RequestType::query()->whereKey($dossier->request_type_id)->value('name'),
            'urgence' => (string) UrgencyLevel::query()->whereKey($dossier->urgency_level_id)->value('name'),
            'échéance' => (string) $dossier->due_at?->format('d/m/Y'),
        ];
    }
}
