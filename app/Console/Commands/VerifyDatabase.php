<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('db:verify')]
#[Description('Lit chaque table pour détecter une table endommagée (lecture seule).')]
class VerifyDatabase extends Command
{
    public function handle(): int
    {
        try {
            $tables = collect(DB::select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0]);
        } catch (Throwable $exception) {
            $this->error('Base de données injoignable : '.$exception->getMessage());

            return self::FAILURE;
        }

        $broken = [];

        foreach ($tables as $table) {
            try {
                DB::table($table)->limit(1)->get();
            } catch (Throwable $exception) {
                $broken[$table] = preg_replace('/\s+/', ' ', $exception->getMessage());
            }
        }

        // Tables connues du moteur InnoDB mais invisibles (fichier .frm perdu) : « fantômes ».
        foreach ($this->innodbTables()->diff($tables->map(fn ($t) => strtolower($t))) as $orphan) {
            $broken[$orphan] = 'table fantôme : présente dans le dictionnaire InnoDB, mais sa définition (.frm) a disparu';
        }

        if ($broken === []) {
            $this->info("Base saine : {$tables->count()} table(s) lisibles.");

            return self::SUCCESS;
        }

        $this->error(count($broken).' table(s) illisible(s) sur '.$tables->count().' :');

        foreach ($broken as $table => $message) {
            $this->line("  - {$table} : ".mb_substr($message, 0, 160));
        }

        $this->line('Réparation : lancer lanceur\\reparer-table.ps1 ; vos autres tables ne sont pas touchées.');

        return self::FAILURE;
    }

    /**
     * Noms des tables InnoDB de la base selon le dictionnaire du moteur (MariaDB / MySQL 5.7 :
     * INNODB_SYS_TABLES, MySQL 8 : INNODB_TABLES). Vide si la vue n'est pas accessible.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function innodbTables(): \Illuminate\Support\Collection
    {
        $prefix = strtolower(DB::getDatabaseName()).'/';

        foreach (['INNODB_SYS_TABLES', 'INNODB_TABLES'] as $view) {
            try {
                return collect(DB::select("SELECT NAME AS name FROM information_schema.{$view} WHERE LOWER(NAME) LIKE ?", [$prefix.'%']))
                    ->map(fn ($row) => substr(strtolower($row->name), strlen($prefix)))
                    ->reject(fn (string $name) => str_contains($name, '#'))
                    ->values();
            } catch (Throwable) {
                continue;
            }
        }

        return collect();
    }
}
