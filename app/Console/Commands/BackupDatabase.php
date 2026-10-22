<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

#[Signature('db:backup {--keep= : Nombre de sauvegardes à conserver (défaut : BACKUP_KEEP ou 14)}')]
#[Description('Sauvegarde la base de données (mysqldump) dans BACKUP_PATH et supprime les plus anciennes.')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $connection = config('database.connections.'.config('database.default'));
        $directory = env('BACKUP_PATH') ?: storage_path('app/private/backups');
        $keep = max(1, (int) ($this->option('keep') ?: env('BACKUP_KEEP', 14)));

        File::ensureDirectoryExists($directory);

        $file = $directory.DIRECTORY_SEPARATOR.$connection['database'].'_'.now()->format('Ymd_His').'.sql';
        $partial = $file.'.partial';

        $process = new Process(array_filter([
            $this->mysqldump(),
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            '--default-character-set=utf8mb4',
            $connection['database'],
        ]), null, ['MYSQL_PWD' => (string) $connection['password']], null, 300);

        $handle = fopen($partial, 'wb');
        $process->run(function (string $type, string $buffer) use ($handle) {
            if ($type === Process::OUT) {
                fwrite($handle, $buffer);
            }
        });
        fclose($handle);

        if (! $process->isSuccessful() || ! $this->isComplete($partial)) {
            @unlink($partial);
            $this->error('Sauvegarde échouée : '.trim($process->getErrorOutput() ?: 'fichier incomplet'));

            return self::FAILURE;
        }

        rename($partial, $file);

        $old = collect(File::glob($directory.DIRECTORY_SEPARATOR.$connection['database'].'_*.sql'))->sort()->values();
        $old->slice(0, max(0, $old->count() - $keep))->each(fn (string $path) => @unlink($path));

        $this->info('Sauvegarde créée : '.$file.' ('.number_format(filesize($file) / 1024, 0, ',', ' ').' Ko, '.min($old->count(), $keep).' conservée(s)).');

        return self::SUCCESS;
    }

    private function mysqldump(): string
    {
        foreach ([env('MYSQLDUMP_PATH'), 'C:\\xamp\\mysql\\bin\\mysqldump.exe', 'C:\\xampp\\mysql\\bin\\mysqldump.exe'] as $path) {
            if ($path && is_file($path)) {
                return $path;
            }
        }

        return 'mysqldump';
    }

    private function isComplete(string $path): bool
    {
        $size = filesize($path);

        if ($size < 1024) {
            return false;
        }

        $handle = fopen($path, 'rb');
        fseek($handle, -200, SEEK_END);
        $tail = (string) fread($handle, 200);
        fclose($handle);

        return str_contains($tail, 'Dump completed');
    }
}
