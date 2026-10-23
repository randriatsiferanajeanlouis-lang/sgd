<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class NoModalFormTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_every_form_is_displayed_in_the_page_without_a_modal(): void
    {
        Storage::fake('dossiers');
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $staff = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($staff, [$this->makeUser(Role::STAFF, 'TECHNIQUE')]);

        $pages = [
            [$admin, route('admin.users.create'), 'Créer un utilisateur'],
            [$admin, route('admin.users.edit', $staff), $staff->name],
        ];

        foreach ($pages as [$user, $url, $title]) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertSee($title)
                ->assertSee('id="page-form"', false)
                ->assertDontSee('id="modal-form"', false)
                ->assertDontSee('fixed inset-0 z-50 overflow-y-auto', false);
        }

        // Pages React : même formulaire en page (FormPage, id « page-form »), sans modale.
        $this->actingAs($staff)->get(route('password.change'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ChangePassword'));
        $this->assertStringContainsString('<FormPage', (string) file_get_contents(resource_path('js/pages/Auth/ChangePassword.jsx')));
        $this->actingAs($staff)->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Profile/Edit'));
        $this->assertStringContainsString('<FormPage', (string) file_get_contents(resource_path('js/pages/Profile/Edit.jsx')));
        $this->actingAs($staff)->get(route('dossiers.edit', $dossier))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dossiers/Edit'));
        $this->assertStringContainsString('<FormPage', (string) file_get_contents(resource_path('js/pages/Dossiers/Edit.jsx')));
        $this->actingAs($admin)->get(route('admin.departments.create'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Departments/Form')->where('department.id', null));
        $this->get(route('admin.departments.edit', Department::query()->firstWhere('code', 'RSE')))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Departments/Form')->where('department.code', 'RSE'));
        $this->assertStringContainsString('<FormPage', (string) file_get_contents(resource_path('js/pages/Admin/Departments/Form.jsx')));
        $this->assertStringContainsString("id = 'page-form'", (string) file_get_contents(resource_path('js/components/form/FormPage.jsx')));

        $this->assertFileDoesNotExist(resource_path('views/components/form/modal.blade.php'));
    }
}
