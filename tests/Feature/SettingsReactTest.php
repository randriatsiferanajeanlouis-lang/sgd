<?php

namespace Tests\Feature;

use App\Models\RequestType;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UrgencyLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Page « Paramètres » en React : types de demandes, urgences, réglages
 * généraux enregistrés ligne par ligne, onglet demandé dans l'adresse.
 */
class SettingsReactTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_the_page_carries_every_editable_row_and_the_rights(): void
    {
        $admin = User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create();

        $this->actingAs($admin)->get(route('admin.settings.index', ['tab' => 'urgencies']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Index')
            ->where('title', 'Paramètres')
            ->where('initialTab', 'urgencies')
            ->has('requestTypes', RequestType::query()->count())
            ->has('urgencyLevels', UrgencyLevel::query()->count())
            ->has('settings', Setting::query()->count())
            ->has('scopes', 3)
            ->where('can.manageRequestTypes', true)
            ->where('can.manageUrgencies', true)
            ->where('can.manageSettings', true)
            ->where('urls.storeRequestType', route('admin.settings.request-types.store')));
    }

    public function test_rows_are_saved_from_the_react_page_and_return_to_it(): void
    {
        $admin = User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create();
        $inertia = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'];
        $urgent = UrgencyLevel::query()->firstWhere('code', 'URGENT');

        $this->actingAs($admin)->from(route('admin.settings.index'))->withHeaders($inertia)
            ->put(route('admin.settings.urgency-levels.update', $urgent), ['name' => 'Urgent', 'response_delay_hours' => 48, 'is_active' => true])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status', 'Niveau d’urgence mis à jour.');
        $this->assertSame(48, $urgent->fresh()->response_delay_hours);

        $this->from(route('admin.settings.index'))->withHeaders($inertia)
            ->post(route('admin.settings.request-types.store'), ['name' => 'Urgent', 'scope' => 'both'])
            ->assertSessionHas('status', 'Type de demande ajouté.');

        $this->from(route('admin.settings.index'))->withHeaders($inertia)
            ->put(route('admin.settings.urgency-levels.update', $urgent), ['name' => 'Très urgent', 'response_delay_hours' => 0])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors(['name' => 'Ce nom est déjà utilisé.', 'response_delay_hours']);
    }
}
