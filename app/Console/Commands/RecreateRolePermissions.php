<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

#[Signature('db:recreate-role-permissions')]
#[Description('Recrée la table role_permissions si elle est illisible ou absente (refuse d’agir si elle est saine).')]
class RecreateRolePermissions extends Command
{
    private const TABLE = 'role_permissions';

    private const TEMP_DATABASE = 'sgd_reparation_tmp';

    public function handle(): int
    {
        if ($this->isReadable()) {
            $this->warn('La table role_permissions est saine : rien à faire.');

            return self::FAILURE;
        }

        DB::statement('DROP TABLE IF EXISTS `'.self::TABLE.'`');

        try {
            $this->createTable();
        } catch (QueryException $exception) {
            // 1050 / 1813 : MariaDB garde la table dans son dictionnaire interne (et parfois son
            // fichier .ibd) alors que son .frm a disparu (table « fantôme ») : purge puis recréation.
            if (! in_array((int) ($exception->errorInfo[1] ?? 0), [1050, 1813], true)) {
                throw $exception;
            }

            $this->warn('Table fantôme dans le dictionnaire de MariaDB : purge...');
            $this->purgeOrphan();
            $this->createTable();
        }

        if (! $this->isReadable()) {
            $this->error('La table n’a pas pu être recréée.');

            return self::FAILURE;
        }

        $this->info('Table role_permissions recréée (vide). Lancer ensuite : php artisan roles:restore-defaults');

        return self::SUCCESS;
    }

    private function createTable(): void
    {
        (require database_path('migrations/2026_09_30_100300_create_role_permissions_table.php'))->up();
    }

    /**
     * Méthode documentée par MariaDB (« InnoDB data dictionary troubleshooting ») : fournir
     * un .frm de même structure pour que DROP TABLE efface l'entrée orpheline du dictionnaire.
     */
    private function purgeOrphan(): void
    {
        $dataDir = rtrim((string) DB::selectOne('SELECT @@datadir AS dir')->dir, '\\/');
        $databaseDir = $dataDir.DIRECTORY_SEPARATOR.strtolower(DB::getDatabaseName());
        $frm = $databaseDir.DIRECTORY_SEPARATOR.self::TABLE.'.frm';

        if (! is_dir($databaseDir)) {
            throw new RuntimeException("Dossier de la base introuvable ({$databaseDir}) : la réparation doit être lancée sur le serveur de la base.");
        }

        try {
            DB::statement('CREATE DATABASE IF NOT EXISTS `'.self::TEMP_DATABASE.'`');
            DB::statement('DROP TABLE IF EXISTS `'.self::TEMP_DATABASE.'`.`'.self::TABLE.'`');
            DB::statement('CREATE TABLE `'.self::TEMP_DATABASE.'`.`'.self::TABLE.'` (
                `role_id` bigint unsigned NOT NULL,
                `permission_id` bigint unsigned NOT NULL,
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`role_id`, `permission_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $template = $dataDir.DIRECTORY_SEPARATOR.self::TEMP_DATABASE.DIRECTORY_SEPARATOR.self::TABLE.'.frm';

            if (! copy($template, $frm)) {
                throw new RuntimeException("Impossible de copier {$template} vers {$frm}.");
            }

            DB::statement('FLUSH TABLES');
            DB::statement('DROP TABLE IF EXISTS `'.self::TABLE.'`');
        } finally {
            DB::statement('DROP DATABASE IF EXISTS `'.self::TEMP_DATABASE.'`');
        }

        // Fichiers résiduels éventuels (un .ibd recréé lors d'une tentative précédente).
        foreach (['.frm', '.ibd'] as $extension) {
            $path = $databaseDir.DIRECTORY_SEPARATOR.self::TABLE.$extension;

            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function isReadable(): bool
    {
        try {
            DB::table(self::TABLE)->limit(1)->get();

            return Schema::hasTable(self::TABLE);
        } catch (Throwable) {
            return false;
        }
    }
}
