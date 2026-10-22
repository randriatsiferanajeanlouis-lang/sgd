<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecreateRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_a_healthy_table_is_never_touched(): void
    {
        $before = \DB::table('role_permissions')->count();

        $this->artisan('db:recreate-role-permissions')->expectsOutputToContain('saine')->assertFailed();

        $this->assertSame($before, \DB::table('role_permissions')->count());
    }

    public function test_a_lost_table_is_recreated_and_the_default_permissions_restored(): void
    {
        Schema::drop('role_permissions');

        $this->artisan('db:recreate-role-permissions')->assertSuccessful();
        $this->assertTrue(Schema::hasTable('role_permissions'));
        $this->assertSame(0, \DB::table('role_permissions')->count());

        $this->artisan('roles:restore-defaults')->assertSuccessful();

        $this->assertTrue(Role::query()->where('name', Role::DEPARTMENT_HEAD)->firstOrFail()->hasPermission('view_departments'));
        $this->assertTrue(Role::query()->where('name', Role::HR_MANAGER)->firstOrFail()->hasPermission('manage_external_requests'));
        $this->assertTrue(Role::query()->where('name', Role::ADMIN)->firstOrFail()->hasPermission('manage_users'));
        $this->artisan('db:verify')->assertSuccessful();
    }

    /**
     * Panne réelle du 16/10/2026 : MariaDB garde la table dans son dictionnaire mais son .frm
     * a disparu (« table fantôme ») ; CREATE TABLE échoue (1050) et SELECT aussi (1146).
     */
    public function test_a_ghost_table_left_in_the_innodb_dictionary_is_purged_and_recreated(): void
    {
        $dataDir = rtrim((string) \DB::selectOne('SELECT @@datadir AS dir')->dir, '\\/');
        $frm = $dataDir.DIRECTORY_SEPARATOR.strtolower(\DB::getDatabaseName()).DIRECTORY_SEPARATOR.'role_permissions.frm';

        if (! is_file($frm)) {
            $this->markTestSkipped('Fichiers de la base inaccessibles depuis PHP (serveur de base distant).');
        }

        \DB::statement('FLUSH TABLES');
        unlink($frm);
        \DB::statement('FLUSH TABLES');

        $this->artisan('db:verify')->expectsOutputToContain('table fantôme')->assertFailed();

        $this->artisan('db:recreate-role-permissions')->expectsOutputToContain('fantôme')->assertSuccessful();
        $this->artisan('roles:restore-defaults')->assertSuccessful();

        $this->assertTrue(Role::query()->where('name', Role::DEPARTMENT_HEAD)->firstOrFail()->hasPermission('view_departments'));
        $this->artisan('db:verify')->expectsOutputToContain('Base saine')->assertSuccessful();
        $this->assertSame([], \DB::select("SHOW DATABASES LIKE 'sgd_reparation_tmp'"), 'La base temporaire est supprimée.');
    }
}
