<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Dossier;
use App\Models\Permission;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UrgencyLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
        $this->admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
    }

    public function test_changing_an_external_delay_applies_to_new_requests(): void
    {
        $urgent = UrgencyLevel::query()->where('code', 'URGENT')->first();
        $this->actingAs($this->admin)->put(route('admin.settings.urgency-levels.update', $urgent), [
            'name' => 'Urgent', 'response_delay_hours' => 48, 'is_active' => '1',
        ])->assertRedirect();

        $hr = User::query()->firstWhere('username', 'rh');
        $hr->update(['must_change_password' => false]);
        $this->actingAs($hr)->post(route('external-requests.store'), [
            'requester_name' => 'Test', 'subject' => 'Délai',
            'requester_organization' => 'Org', 'requester_phone' => '034 00 000 00', 'requester_email' => 'test@example.com', 'description' => 'Test',
            'request_type_id' => RequestType::query()->where('code', 'AUTRE')->value('id'), 'request_type_other' => 'Demande de délai',
            'received_at' => now()->toDateString(), 'reception_channel' => 'email',
            'urgency_level_id' => $urgent->id,
            'recipients' => [$this->makeUser(Role::DEPARTMENT_HEAD, 'RSE')->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(now()->addHours(48)->format('Y-m-d H:i'), Dossier::query()->latest('id')->first()->due_at->format('Y-m-d H:i'));
    }

    public function test_created_type_is_usable_and_deactivated_type_is_refused(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.request-types.store'), ['name' => 'Demande de véhicule', 'scope' => 'internal'])->assertRedirect();
        $type = RequestType::query()->firstWhere('name', 'Demande de véhicule');
        $this->assertTrue($type->is_active);

        $employee = $this->makeUser(Role::STAFF);
        $this->actingAs($employee)->get(route('dossiers.create'))->assertSee('Demande de véhicule');
        $this->sendInternalRequest($employee, [$this->makeUser(Role::STAFF, 'RSE')], ['request_type_id' => $type->id]);

        $this->actingAs($this->admin)->put(route('admin.settings.request-types.update', $type), ['name' => 'Demande de véhicule', 'scope' => 'internal']);
        $this->assertFalse($type->fresh()->is_active);

        $this->actingAs($employee)->get(route('dossiers.create'))->assertDontSee('Demande de véhicule');
        $this->post(route('dossiers.store'), ['subject' => 'x', 'request_type_id' => $type->id, 'due_at' => now()->toDateString(), 'recipients' => [$this->admin->id]])
            ->assertSessionHasErrors('request_type_id');
        $this->assertSame($type->id, Dossier::query()->first()->request_type_id, 'les dossiers existants gardent leur type');
    }

    public function test_permission_change_takes_effect_immediately(): void
    {
        $staff = Role::query()->firstWhere('name', Role::STAFF);
        $ids = $staff->permissions()->pluck('permissions.id')->reject(fn ($id) => $id === Permission::query()->where('name', 'create_internal_request')->value('id'))->all();

        $this->actingAs($this->admin)->put(route('admin.settings.roles.permissions', $staff), ['permissions' => $ids])->assertRedirect();

        $this->actingAs($this->makeUser(Role::STAFF))->get(route('dossiers.create'))->assertForbidden();
    }

    public function test_reference_prefix_setting_is_traced_and_applied(): void
    {
        $prefix = Setting::query()->where('key', 'dossiers.reference_prefix')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.settings.values.update', $prefix), ['value' => 'COR'])->assertRedirect();
        $this->assertTrue(AuditLog::query()->where('action', 'setting.updated')->exists());

        $dossier = $this->sendInternalRequest($this->makeUser(Role::STAFF), [$this->makeUser(Role::STAFF, 'RSE')]);
        $this->assertStringStartsWith('COR-'.now()->year.'-', $dossier->reference);
    }

    public function test_only_admin_can_change_settings(): void
    {
        $head = $this->makeUser(Role::DEPARTMENT_HEAD);
        $this->actingAs($head);

        $this->get(route('admin.settings.index'))->assertForbidden();
        $this->post(route('admin.settings.request-types.store'), ['name' => 'Pirate', 'scope' => 'both'])->assertForbidden();
        $this->put(route('admin.settings.urgency-levels.update', UrgencyLevel::query()->first()), ['name' => 'x', 'response_delay_hours' => 1])->assertForbidden();
        $this->put(route('admin.settings.roles.permissions', $head->role), ['permissions' => Permission::query()->pluck('id')->all()])->assertForbidden();
        $this->put(route('admin.settings.values.update', Setting::query()->first()), ['value' => 'X'])->assertForbidden();

        $this->assertFalse($head->fresh()->hasPermission('manage_users'));
    }
}
