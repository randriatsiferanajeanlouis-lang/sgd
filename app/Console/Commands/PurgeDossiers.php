<?php

namespace App\Console\Commands;

use App\Notifications\DeadlineOverdueAlert;
use App\Notifications\DossierNotification;
use App\Services\AuditTrail;
use App\Services\DocumentStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('dossiers:purge {--force : Ne pas demander de confirmation} {--no-backup : Ne pas sauvegarder la base avant}')]
#[Description('Supprime tous les dossiers (données de démonstration) avec leurs pièces, historiques et notifications. Les comptes, départements et paramètres sont conservés.')]
class PurgeDossiers extends Command
{
    /** Tables vidées, dans l'ordre imposé par les clés étrangères. */
    private const TABLES = [
        'documents', 'reminders', 'dossier_validations', 'dossier_transmissions', 'responses',
        'dossier_activity_logs', 'dossier_recipients', 'dossiers', 'external_requesters', 'dossier_sequences',
    ];

    private const NOTIFICATIONS = [DossierNotification::class, DeadlineOverdueAlert::class];

    public function handle(AuditTrail $auditTrail): int
    {
        $counts = collect(self::TABLES)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);
        $counts['notifications'] = DB::table('notifications')->whereIn('type', self::NOTIFICATIONS)->count();

        if ($counts['dossiers'] === 0 && $counts['notifications'] === 0) {
            $this->info('Aucun dossier à supprimer.');

            return self::SUCCESS;
        }

        $this->table(['Table', 'Lignes supprimées'], $counts->map(fn (int $count, string $table) => [$table, $count])->values());

        if (! $this->option('force') && ! $this->confirm('Supprimer définitivement tous ces dossiers ?')) {
            $this->warn('Opération annulée.');

            return self::FAILURE;
        }

        if (! $this->option('no-backup') && $this->call('db:backup') !== self::SUCCESS) {
            $this->error('Sauvegarde impossible : aucune suppression effectuée.');

            return self::FAILURE;
        }

        $files = DB::table('documents')->get(['disk', 'path']);

        // L'historique est protégé contre la suppression par un trigger : il est
        // levé le temps de l'opération puis recréé, même en cas d'échec.
        DB::unprepared('DROP TRIGGER IF EXISTS dossier_activity_logs_no_delete');

        try {
            DB::transaction(function () {
                DB::table('documents')->update(['previous_version_id' => null]);

                foreach (self::TABLES as $table) {
                    DB::table($table)->delete();
                }

                DB::table('notifications')->whereIn('type', self::NOTIFICATIONS)->delete();
            });
        } catch (Throwable $exception) {
            $this->error('Suppression annulée : '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            DB::unprepared("CREATE TRIGGER dossier_activity_logs_no_delete BEFORE DELETE ON dossier_activity_logs
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historique en ajout seul : suppression interdite.'");
        }

        foreach ($files as $file) {
            Storage::disk($file->disk)->delete($file->path);
        }

        $disk = Storage::disk(DocumentStorage::DISK);
        foreach ($disk->directories() as $directory) {
            $disk->deleteDirectory($directory);
        }

        $auditTrail->record('dossiers.purged', oldValues: $counts->all(), comment: 'Suppression des dossiers de démonstration');

        $this->info($counts['dossiers'].' dossier(s) supprimé(s). La numérotation repart à 1.');

        return self::SUCCESS;
    }
}
