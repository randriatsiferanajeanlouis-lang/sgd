<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DossierDataRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_dossiers_stay_visible_after_a_password_reset(): void
    {
        $employee = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create();
        $other = User::factory()->withRole(Role::STAFF, 'TECHNIQUE')->create();
        $sent = $this->dossier($employee);
        $received = $this->dossier($other, recipient: $employee);

        $this->actingAs(User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create())
            ->post(route('admin.users.reset-password', $employee))
            ->assertSessionHas('temporary_password');
        $temporary = session('temporary_password')['password'];

        $this->actingAs($employee->fresh())->get(route('dossiers.index'))->assertRedirect(route('password.change'));

        $this->put(route('password.change.update'), [
            'current_password' => $temporary,
            'password' => 'NouveauMotDePasse2026',
            'password_confirmation' => 'NouveauMotDePasse2026',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dossiers.index', ['box' => 'all']))->assertOk()->assertSee($sent->reference)->assertSee($received->reference);
    }

    public function test_purge_removes_every_dossier_but_keeps_accounts_and_history_protection(): void
    {
        Storage::fake(DocumentStorage::DISK);
        $employee = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create();
        $dossier = $this->dossier($employee, recipient: User::factory()->withRole(Role::STAFF, 'TECHNIQUE')->create());
        Storage::disk(DocumentStorage::DISK)->put('2026/'.$dossier->reference.'/piece.pdf', 'contenu');
        Document::query()->create([
            'dossier_id' => $dossier->id, 'document_key' => (string) Str::uuid(), 'original_name' => 'piece.pdf',
            'disk' => DocumentStorage::DISK, 'path' => '2026/'.$dossier->reference.'/piece.pdf', 'uploaded_by' => $employee->id,
        ]);
        DB::table('dossier_activity_logs')->insert(['dossier_id' => $dossier->id, 'action' => 'created', 'created_at' => now()]);
        $users = User::query()->count();

        $this->artisan('dossiers:purge', ['--force' => true, '--no-backup' => true])->assertSuccessful();

        foreach (['dossiers', 'dossier_recipients', 'documents', 'dossier_activity_logs', 'dossier_sequences'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertSame($users, User::query()->count());
        $this->assertSame([], Storage::disk(DocumentStorage::DISK)->allFiles());
        $this->assertTrue(AuditLog::query()->where('action', 'dossiers.purged')->exists());

        $this->assertNotEmpty(DB::select("SHOW TRIGGERS WHERE `Trigger` = 'dossier_activity_logs_no_delete'"));
    }

    public function test_purge_asks_for_confirmation(): void
    {
        $this->dossier(User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create());

        $this->artisan('dossiers:purge', ['--no-backup' => true])
            ->expectsConfirmation('Supprimer définitivement tous ces dossiers ?', 'no')
            ->assertFailed();

        $this->assertSame(1, Dossier::query()->count());
    }

    private function dossier(User $creator, ?User $recipient = null): Dossier
    {
        $dossier = Dossier::factory()->create(['created_by' => $creator->id, 'originating_department_id' => $creator->department_id]);

        if ($recipient) {
            $dossier->recipients()->create(['department_id' => $recipient->department_id, 'assigned_user_id' => $recipient->id]);
        }

        return $dossier;
    }
}
