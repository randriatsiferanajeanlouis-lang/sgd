<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\DossierOrigin;
use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Enums\RecipientType;
use App\Enums\RequestScope;
use App\Enums\ResponseType;
use App\Enums\TransmissionType;
use App\Models\Department;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\DossierActivityLog;
use App\Models\ExternalRequester;
use App\Models\Permission;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UrgencyLevel;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_reference_data_is_seeded(): void
    {
        $this->assertSame(
            ['ADMINISTRATION', 'RSE', 'COLLECTE', 'PRODUCTION', 'ARMEMENT', 'TECHNIQUE'],
            Department::query()->orderBy('sort_order')->pluck('code')->all(),
        );

        $this->assertSame(
            ['TRES_URGENT' => 24, 'URGENT' => 72, 'NORMAL' => 168],
            UrgencyLevel::query()->orderBy('sort_order')->pluck('response_delay_hours', 'code')->all(),
        );

        $this->assertSame(
            [Role::ADMIN, Role::DEPARTMENT_HEAD, Role::HR_MANAGER, Role::STAFF],
            Role::query()->orderBy('name')->pluck('name')->all(),
        );

        $this->assertTrue(RequestType::query()->availableFor(RequestScope::External)->exists());
        $this->assertTrue(RequestType::query()->availableFor(RequestScope::Internal)->exists());
        $this->assertSame('hr_manager', Setting::valueFor('external_requests.entry_role'));
    }

    public function test_seeders_are_idempotent(): void
    {
        $this->seed();

        $this->assertSame(6, Department::query()->count());
        $this->assertSame(4, Role::query()->count());
        $this->assertSame(3, UrgencyLevel::query()->count());
    }

    public function test_reseeding_roles_keeps_permissions_changed_by_the_administrator(): void
    {
        $staff = Role::query()->firstWhere('name', Role::STAFF);
        $staff->permissions()->detach(Permission::query()->firstWhere('name', 'send_reminder'));

        $this->seed(RoleSeeder::class);

        $this->assertFalse($staff->fresh()->hasPermission('send_reminder'));
        $this->assertTrue($staff->fresh()->hasPermission('create_internal_request'));
    }

    public function test_user_email_is_optional_and_username_is_unique(): void
    {
        $user = $this->userWithRole(Role::STAFF, ['username' => 'jean', 'email' => null]);

        $this->assertNull($user->fresh()->email);
        $this->assertSame('COLLECTE', $user->department->code);

        $this->expectException(QueryException::class);
        $this->userWithRole(Role::STAFF, ['username' => 'jean', 'email' => null]);
    }

    public function test_dossier_has_several_recipients_with_independent_statuses(): void
    {
        $dossier = Dossier::factory()->create();
        $production = Department::query()->firstWhere('code', 'PRODUCTION');
        $technique = Department::query()->firstWhere('code', 'TECHNIQUE');
        $first = $this->userWithRole(Role::STAFF, ['department_id' => $production->id]);
        $second = $this->userWithRole(Role::STAFF, ['department_id' => $technique->id]);

        $dossier->recipients()->create(['department_id' => $production->id, 'assigned_user_id' => $first->id, 'status' => RecipientStatus::Answered]);
        $dossier->recipients()->create([
            'department_id' => $technique->id,
            'assigned_user_id' => $second->id,
            'recipient_type' => RecipientType::Information,
        ]);

        $statuses = $dossier->recipients()->with('department')->get()
            ->mapWithKeys(fn ($recipient) => [$recipient->department->code => $recipient->status]);

        $this->assertSame(RecipientStatus::Answered, $statuses['PRODUCTION']);
        $this->assertSame(RecipientStatus::Received, $statuses['TECHNIQUE']);
        $this->assertEqualsCanonicalizing(
            ['PRODUCTION', 'TECHNIQUE'],
            $dossier->recipientDepartments->pluck('code')->all(),
        );
        $this->assertTrue($production->receivedDossiers->contains($dossier));
        $this->assertSame(DossierStatus::Sent, $dossier->fresh()->status);
    }

    public function test_two_people_of_the_same_department_can_receive_the_same_dossier_but_nobody_twice(): void
    {
        $dossier = Dossier::factory()->create();
        $rse = Department::query()->firstWhere('code', 'RSE');
        $first = $this->userWithRole(Role::STAFF, ['department_id' => $rse->id]);
        $second = $this->userWithRole(Role::STAFF, ['department_id' => $rse->id]);

        $dossier->recipients()->create(['department_id' => $rse->id, 'assigned_user_id' => $first->id]);
        $dossier->recipients()->create(['department_id' => $rse->id, 'assigned_user_id' => $second->id]);
        $this->assertSame(2, $dossier->recipients()->count());

        $this->expectException(QueryException::class);
        $dossier->recipients()->create(['department_id' => $rse->id, 'assigned_user_id' => $first->id]);
    }

    public function test_external_dossier_requires_an_external_requester(): void
    {
        $external = Dossier::factory()->external()->create();

        $this->assertTrue($external->isExternal());
        $this->assertInstanceOf(ExternalRequester::class, $external->externalRequester);
        $this->assertTrue($external->externalRequester->dossiers->contains($external));

        $this->expectException(QueryException::class);
        Dossier::factory()->create([
            'origin' => DossierOrigin::External,
            'originating_department_id' => null,
            'external_requester_id' => null,
        ]);
    }

    public function test_internal_dossier_requires_an_originating_department(): void
    {
        $this->expectException(QueryException::class);

        Dossier::factory()->create(['originating_department_id' => null]);
    }

    public function test_urgency_level_computes_due_date_in_hours(): void
    {
        $receivedAt = Carbon::parse('2026-10-01 08:00:00');

        $this->assertSame(
            '2026-10-04 08:00:00',
            UrgencyLevel::query()->firstWhere('code', 'URGENT')->dueDateFrom($receivedAt)->toDateTimeString(),
        );
        $this->assertSame('2026-10-01 08:00:00', $receivedAt->toDateTimeString());
    }

    public function test_responses_transmissions_and_reminders_are_linked_to_their_recipient(): void
    {
        $dossier = Dossier::factory()->create();
        $armement = Department::query()->firstWhere('code', 'ARMEMENT');
        $recipient = $dossier->recipients()->create([
            'department_id' => $armement->id,
            'assigned_user_id' => $this->userWithRole(Role::STAFF, ['department_id' => $armement->id])->id,
        ]);

        $recipient->transmissions()->create([
            'dossier_id' => $dossier->id,
            'from_department_id' => $dossier->originating_department_id,
            'to_department_id' => $recipient->department_id,
            'transmitted_by' => $dossier->created_by,
            'type' => TransmissionType::Initial,
        ]);

        $recipient->responses()->create([
            'dossier_id' => $dossier->id,
            'department_id' => $recipient->department_id,
            'author_id' => $dossier->created_by,
            'type' => ResponseType::Answer,
            'content' => 'Réponse du département Armement.',
        ]);

        $recipient->reminders()->create(['dossier_id' => $dossier->id, 'scheduled_at' => now()->subHour()]);

        $dossier->refresh();
        $this->assertSame('ARMEMENT', $dossier->transmissions->first()->toDepartment->code);
        $this->assertSame(ResponseType::Answer, $dossier->responses->first()->type);
        $this->assertTrue($dossier->responses->first()->recipient->is($recipient));
        $this->assertSame(1, $dossier->reminders()->due()->count());
    }

    public function test_documents_are_versioned(): void
    {
        $dossier = Dossier::factory()->create();
        $key = (string) Str::uuid();
        $attributes = [
            'dossier_id' => $dossier->id,
            'document_key' => $key,
            'original_name' => 'courrier.pdf',
            'path' => 'dossiers/courrier-v1.pdf',
            'uploaded_by' => $dossier->created_by,
        ];

        $first = Document::query()->create($attributes);
        $first->update(['status' => DocumentStatus::Replaced]);
        $second = Document::query()->create([
            ...$attributes,
            'version' => 2,
            'previous_version_id' => $first->id,
            'path' => 'dossiers/courrier-v2.pdf',
        ]);

        $this->assertSame([1, 2], $second->versions->pluck('version')->all());
        $this->assertTrue($second->previousVersion->is($first));
        $this->assertTrue($first->nextVersion->is($second));
        $this->assertSame([$second->id], $dossier->currentDocuments->pluck('id')->all());

        $this->expectException(QueryException::class);
        Document::query()->create([...$attributes, 'version' => 2]);
    }

    public function test_activity_log_cannot_be_modified_or_deleted_through_the_model(): void
    {
        $log = $this->createActivityLog();

        $this->assertSame('dossier', $log->subject_type);
        $this->assertTrue($log->subject->is($log->dossier));

        $this->expectException(LogicException::class);
        $log->update(['description' => 'Falsifié']);
    }

    public function test_activity_log_deletion_through_the_model_is_refused(): void
    {
        $log = $this->createActivityLog();

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_activity_log_is_protected_at_database_level(): void
    {
        $log = $this->createActivityLog();

        try {
            DB::table('dossier_activity_logs')->where('id', $log->id)->update(['description' => 'Falsifié']);
            $this->fail('La mise à jour directe aurait dû être refusée.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('modification interdite', $exception->getMessage());
        }

        try {
            DB::table('dossier_activity_logs')->where('id', $log->id)->delete();
            $this->fail('La suppression directe aurait dû être refusée.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('suppression interdite', $exception->getMessage());
        }

        $this->assertDatabaseHas('dossier_activity_logs', ['id' => $log->id, 'description' => 'Dossier enregistré.']);
    }

    public function test_dossier_with_history_cannot_be_deleted(): void
    {
        $log = $this->createActivityLog();

        $this->expectException(QueryException::class);
        $log->dossier->delete();
    }

    public function test_department_in_use_cannot_be_deleted(): void
    {
        $user = $this->userWithRole(Role::STAFF);

        $this->expectException(QueryException::class);
        $user->department->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $roleName, array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->firstWhere('name', $roleName)->id,
            'department_id' => Department::query()->firstWhere('code', 'COLLECTE')->id,
            ...$attributes,
        ]);
    }

    private function createActivityLog(): DossierActivityLog
    {
        $dossier = Dossier::factory()->create();

        return DossierActivityLog::query()->create([
            'dossier_id' => $dossier->id,
            'user_id' => $dossier->created_by,
            'action' => 'dossier.created',
            'description' => 'Dossier enregistré.',
            'subject_type' => 'dossier',
            'subject_id' => $dossier->id,
            'properties' => ['reference' => $dossier->reference],
        ]);
    }
}
