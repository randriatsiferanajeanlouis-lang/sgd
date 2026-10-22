<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

class DatabaseSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_verify_reports_a_healthy_database(): void
    {
        $this->artisan('db:verify')->expectsOutputToContain('Base saine')->assertSuccessful();
    }

    public function test_restore_defaults_rebuilds_role_permissions_without_removing_extras(): void
    {
        $head = Role::query()->where('name', Role::DEPARTMENT_HEAD)->firstOrFail();
        $extra = \App\Models\Permission::query()->where('name', 'manage_users')->firstOrFail();
        $head->permissions()->sync([$extra->id]);

        $this->artisan('roles:restore-defaults')->assertSuccessful();

        $names = $head->fresh()->permissions->pluck('name');
        $this->assertTrue($names->contains('view_departments'));
        $this->assertTrue($names->contains('validate_dossier'));
        $this->assertTrue($names->contains('manage_users'), 'Une permission ajoutée à la main n’est jamais retirée.');

        $this->assertTrue(Role::query()->where('name', Role::ADMIN)->firstOrFail()->hasPermission('manage_departments'));
    }

    public function test_backup_writes_a_complete_dump_and_keeps_only_the_latest_ones(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sgd-backup-'.uniqid();
        putenv("BACKUP_PATH={$directory}");
        $_ENV['BACKUP_PATH'] = $directory;

        try {
            foreach ([1, 2, 3] as $second) {
                $this->travelTo(now()->addSeconds($second));
                $this->artisan('db:backup', ['--keep' => 2])->assertSuccessful();
            }

            $files = File::glob($directory.DIRECTORY_SEPARATOR.'*.sql');
            $this->assertCount(2, $files, 'Les sauvegardes les plus anciennes sont supprimées.');
            $this->assertEmpty(File::glob($directory.DIRECTORY_SEPARATOR.'*.partial'));

            $dump = file_get_contents(end($files));
            $this->assertStringContainsString('CREATE TABLE `users`', $dump);
            $this->assertStringContainsString('Dump completed', $dump);
            $this->assertStringContainsString('TRIGGER', $dump, 'Les triggers qui protègent l’historique sont sauvegardés.');
        } finally {
            putenv('BACKUP_PATH');
            unset($_ENV['BACKUP_PATH']);
            File::deleteDirectory($directory);
        }
    }

    public function test_database_outage_shows_a_clear_message_instead_of_a_stack_trace(): void
    {
        $pdo = new PDOException('Connection refused');
        $pdo->errorInfo = ['HY000', 2002, 'Connection refused'];

        Route::get('/_test/db-down', fn () => throw new QueryException('mysql', 'select 1', [], $pdo));

        $this->get('/_test/db-down')
            ->assertStatus(503)
            ->assertSee('base de données est arrêtée')
            ->assertDontSee('QueryException');

        $this->getJson('/_test/db-down')
            ->assertStatus(503)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'base de données'));
    }

    public function test_other_query_errors_are_not_masked(): void
    {
        $pdo = new PDOException('Duplicate');
        $pdo->errorInfo = ['23000', 1062, 'Duplicate'];

        Route::get('/_test/db-other', fn () => throw new QueryException('mysql', 'insert', [], $pdo));

        $this->get('/_test/db-other')->assertStatus(500);
    }

    public function test_backup_is_scheduled_twice_a_day(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command, 'db:backup'));

        $this->assertCount(1, $events);
        $this->assertSame('0 12,17 * * *', $events->first()->expression);
    }
}
