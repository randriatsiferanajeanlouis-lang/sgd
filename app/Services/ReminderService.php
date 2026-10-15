<?php

namespace App\Services;

use App\Enums\RecipientStatus;
use App\Enums\ReminderChannel;
use App\Enums\ReminderReason;
use App\Enums\ReminderType;
use App\Models\DossierRecipient;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DeadlineOverdueAlert;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Relances manuelles et alertes d'échéance automatiques. Un délai dépassé
 * ne change jamais le statut : il déclenche seulement des alertes.
 */
class ReminderService
{
    public function __construct(
        private readonly DossierHistory $history,
        private readonly DossierNotifier $notifier,
    ) {}

    public function sendManual(DossierRecipient $recipient, User $sender, ?string $message = null): void
    {
        $dossier = $recipient->dossier;
        $message = filled($message) ? $message : "Rappel concernant le dossier {$dossier->reference}";

        DB::transaction(function () use ($recipient, $sender, $message, $dossier) {
            $reminder = $recipient->reminders()->create([
                'dossier_id' => $dossier->id,
                'sent_by' => $sender->id,
                'type' => ReminderType::Manual,
                'channel' => ReminderChannel::InApp,
                'reason' => ReminderReason::Manual,
                'message' => $message,
                'scheduled_at' => now(),
                'sent_at' => now(),
            ]);

            $this->history->record($dossier, 'reminder.sent', $reminder,
                newValues: ['destinataire' => $recipient->assignedUser->name],
                comment: $message,
            );

            $this->notifier->notify($recipient->assignedUser, 'reminder', "{$sender->name} : {$message}", $dossier);
        });
    }

    /**
     * Parcourt les traitements ouverts : alerte « échéance aujourd’hui » et
     * alerte « délai dépassé », chacune une seule fois par traitement.
     *
     * @return array{due_today: int, overdue: int}
     */
    public function checkDeadlines(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $counts = ['due_today' => 0, 'overdue' => 0];

        $this->openTreatments()
            ->whereBetween('due_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->where('due_at', '>=', $now)
            ->whereDoesntHave('reminders', fn (Builder $reminder) => $reminder->where('reason', ReminderReason::DeadlineToday->value))
            ->each(function (DossierRecipient $recipient) use (&$counts) {
                $this->alert($recipient, ReminderReason::DeadlineToday);
                $counts['due_today']++;
            });

        $this->openTreatments()
            ->where('due_at', '<', $now)
            ->whereDoesntHave('reminders', fn (Builder $reminder) => $reminder->where('reason', ReminderReason::DeadlineOverdue->value))
            ->each(function (DossierRecipient $recipient) use (&$counts) {
                $this->alert($recipient, ReminderReason::DeadlineOverdue);
                $counts['overdue']++;
            });

        return $counts;
    }

    /**
     * @return Builder<DossierRecipient>
     */
    private function openTreatments(): Builder
    {
        return DossierRecipient::query()
            ->with(['dossier.creator', 'dossier.urgencyLevel', 'dossier.externalRequester', 'assignedUser.department'])
            ->whereIn('status', [RecipientStatus::Received->value, RecipientStatus::InProgress->value])
            ->whereNotNull('due_at');
    }

    /**
     * Échéance du jour : le destinataire. Délai dépassé : le destinataire et
     * l'expéditeur (demande interne) ou le RH (demande externe).
     */
    private function alert(DossierRecipient $recipient, ReminderReason $reason): void
    {
        $dossier = $recipient->dossier;
        $overdue = $reason === ReminderReason::DeadlineOverdue;
        $message = $overdue
            ? "Délai dépassé pour le dossier {$dossier->reference} ({$recipient->assignedUser->name})."
            : "Échéance aujourd’hui pour le dossier {$dossier->reference}.";

        // Le super administrateur n'est jamais alerté.
        $people = collect($overdue ? [$recipient->assignedUser, $dossier->creator] : [$recipient->assignedUser])
            ->filter(fn (?User $person) => $person && ! $person->hasRole(Role::ADMIN))
            ->unique('id')
            ->values();

        DB::transaction(function () use ($recipient, $reason, $dossier, $overdue, $message, $people) {
            $reminder = $recipient->reminders()->create([
                'dossier_id' => $dossier->id,
                'type' => ReminderType::Automatic,
                'channel' => ReminderChannel::InApp,
                'reason' => $reason,
                'message' => $message,
                'scheduled_at' => $recipient->due_at,
                'sent_at' => now(),
            ]);

            $this->history->record($dossier, $overdue ? 'deadline.overdue' : 'deadline.today', $reminder,
                newValues: ['destinataire' => $recipient->assignedUser->name, 'échéance' => $recipient->due_at->format('d/m/Y H:i')],
            );

            if ($people->isNotEmpty()) {
                $this->notifier->notify($people->all(), $overdue ? 'deadline_overdue' : 'deadline_today', $message, $dossier);
            }
        });

        if ($overdue) {
            $this->emailOverdueAlert($recipient, $people);
        }
    }

    /**
     * E-mail « délai dépassé » au destinataire et à l'expéditeur. Hors
     * transaction : un serveur de messagerie indisponible n'empêche ni
     * l'alerte dans l'application ni le traitement des autres dossiers.
     *
     * @param  Collection<int, User>  $people
     */
    private function emailOverdueAlert(DossierRecipient $recipient, Collection $people): void
    {
        $people
            ->filter(fn (User $person) => $person->is_active && filled($person->email))
            ->each(function (User $person) use ($recipient) {
                try {
                    $person->notify(new DeadlineOverdueAlert($recipient));
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
    }
}
