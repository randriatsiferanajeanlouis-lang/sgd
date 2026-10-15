<?php

namespace App\Services;

use App\Enums\TransmissionType;
use App\Enums\ValidationStatus;
use App\Models\DossierRecipient;
use App\Models\DossierValidation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Validation hiérarchique : Employé destinataire → Responsable → Valider /
 * Refuser → retour à l'employé, qui répond ensuite au demandeur.
 */
class ValidationService
{
    public function __construct(
        private readonly DossierHistory $history,
        private readonly DossierNotifier $notifier,
    ) {}

    /**
     * Responsable qui valide pour ce destinataire : celui de son département.
     */
    public static function validatorFor(User $employee): ?User
    {
        $manager = $employee->department?->manager;

        return $manager && $manager->is_active && $manager->isNot($employee) ? $manager : null;
    }

    public function request(DossierRecipient $recipient, User $employee, ?string $comment): DossierValidation
    {
        $validator = self::validatorFor($employee) ?? throw new InvalidArgumentException('Aucun responsable actif n’est défini pour votre département.');

        return DB::transaction(function () use ($recipient, $employee, $comment, $validator) {
            $dossier = $recipient->dossier;

            $validation = $recipient->validations()->create([
                'dossier_id' => $dossier->id,
                'requested_by' => $employee->id,
                'validator_id' => $validator->id,
                'request_comment' => $comment,
            ]);

            $dossier->transmissions()->create([
                'dossier_recipient_id' => $recipient->id,
                'from_department_id' => $employee->department_id,
                'to_department_id' => $validator->department_id,
                'transmitted_by' => $employee->id,
                'type' => TransmissionType::Validation,
                'comment' => $comment,
                'transmitted_at' => now(),
            ]);

            $this->history->record($dossier, 'validation.requested', $validation,
                newValues: ['responsable' => $validator->name, 'validation' => ValidationStatus::Pending->label()],
                comment: $comment,
            );

            $this->notifier->notify($validator, 'validation_requested',
                "{$employee->name} vous demande de valider le dossier {$dossier->reference}.", $dossier);

            return $validation;
        });
    }

    public function decide(DossierValidation $validation, User $validator, bool $approved, ?string $comment): void
    {
        if ($validation->status !== ValidationStatus::Pending) {
            throw new LogicException('Cette demande de validation a déjà été traitée.');
        }

        DB::transaction(function () use ($validation, $validator, $approved, $comment) {
            $status = $approved ? ValidationStatus::Approved : ValidationStatus::Rejected;

            $validation->update(['status' => $status, 'decision_comment' => $comment, 'decided_at' => now()]);

            $this->history->record($validation->dossier, $approved ? 'validation.approved' : 'validation.rejected', $validation,
                ['validation' => ValidationStatus::Pending->label()],
                ['validation' => $status->label(), 'responsable' => $validator->name],
                $comment,
            );

            $this->notifier->notify(
                $validation->requester,
                $approved ? 'validation_approved' : 'validation_rejected',
                "{$validator->name} a ".($approved ? 'validé' : 'refusé')." votre demande de validation sur le dossier {$validation->dossier->reference}.",
                $validation->dossier,
            );
        });
    }
}
