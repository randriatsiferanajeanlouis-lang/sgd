<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_profile_shows_identity_and_organisation(): void
    {
        $user = User::factory()->withRole(Role::STAFF, 'TECHNIQUE')->create([
            'first_name' => 'Jean',
            'last_name' => 'Rakoto',
            'job_title' => 'Électricien',
            'phone' => '034 00 000 00',
        ]);

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('title', 'Mon profil')
                ->where('profile.firstName', 'Jean')
                ->where('profile.lastName', 'Rakoto')
                ->where('profile.initials', $user->initials())
                ->where('profile.photoUrl', null)
                ->where('profile.details', [
                    ['label' => 'Nom', 'value' => 'Rakoto'],
                    ['label' => 'Prénom', 'value' => 'Jean'],
                    ['label' => 'Fonction', 'value' => 'Électricien'],
                    ['label' => 'Département', 'value' => 'Technique'],
                    ['label' => 'Rôle', 'value' => 'Employé'],
                    ['label' => 'Téléphone', 'value' => '034 00 000 00'],
                    ['label' => 'E-mail interne', 'value' => $user->email],
                ])
                ->where('urls.edit', route('profile.edit'))
                ->where('urls.logout', route('logout')));

        // Page rendue par React : l'ancienne vue Blade n'existe plus.
        $this->assertFalse(view()->exists('profile.show'));
        $page = (string) file_get_contents(resource_path('js/pages/Profile/Show.jsx'));
        foreach (['Modifier mes coordonnées', 'Déconnexion', 'name="_token"', 'Le département, le rôle et la fonction sont gérés par l’administrateur.'] as $needle) {
            $this->assertStringContainsString($needle, $page);
        }
    }

    public function test_profile_shows_the_photo_and_only_the_connected_user(): void
    {
        Storage::fake('local');
        $user = User::factory()->withRole(Role::STAFF)->create();
        $other = User::factory()->withRole(Role::STAFF)->create(['first_name' => 'Autre']);
        $this->actingAs($user)->put(route('profile.update'), ['photo' => $this->pngUpload()]);

        $this->get(route('profile.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.username', $user->username)
                ->where('profile.photoUrl', route('users.photo', $user)));

        $this->assertNotSame($other->username, $user->username);
    }

    public function test_updating_the_profile_returns_to_the_react_profile_with_a_message(): void
    {
        $user = User::factory()->withRole(Role::STAFF)->create();

        $this->actingAs($user)->followingRedirects()
            ->put(route('profile.update'), ['phone' => '034 99 999 99'])
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('flash.status', 'Votre profil a été mis à jour.')
                ->where('profile.details.5.value', '034 99 999 99'));
    }

    public function test_user_can_update_phone_email_and_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->withRole(Role::STAFF)->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'phone' => '+261 34 11 222 33',
            'email' => 'jean@sogediproma.local',
            'photo' => $this->pngUpload(),
        ])->assertRedirect(route('profile.show'));

        $user->refresh();
        $this->assertSame('+261 34 11 222 33', $user->phone);
        $this->assertSame('jean@sogediproma.local', $user->email);
        Storage::disk('local')->assertExists($user->photo_path);

        $this->get(route('users.photo', $user))->assertOk();
    }

    public function test_edit_page_is_rendered_by_react_with_only_the_editable_fields(): void
    {
        $user = User::factory()->withRole(Role::STAFF)->create(['phone' => '034 00 000 00']);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('title', 'Modifier mon profil')
                ->where('profile', [
                    'phone' => '034 00 000 00',
                    'email' => $user->email,
                    'initials' => $user->initials(),
                    'photoUrl' => null,
                ])
                ->where('urls.update', route('profile.update'))
                ->where('urls.cancel', route('profile.show')));

        $this->assertFalse(view()->exists('profile.edit'));
        $page = (string) file_get_contents(resource_path('js/pages/Profile/Edit.jsx'));
        foreach (['Modifier mes coordonnées', 'name="phone"', 'name="email"', 'name="photo"', 'name="remove_photo"', 'Supprimer la photo actuelle', 'JPG, PNG ou WebP, 2 Mo maximum.', "_method: 'put'", 'forceFormData'] as $needle) {
            $this->assertStringContainsString($needle, $page);
        }
        foreach (['department', 'role_id', 'job_title', 'username'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $page);
        }
    }

    public function test_react_form_uploads_the_photo_and_reloads_the_profile(): void
    {
        Storage::fake('local');
        $user = User::factory()->withRole(Role::STAFF)->create();
        $inertia = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'];

        // Envoi multipart comme le fait la page React : POST + _method=PUT.
        $this->actingAs($user)->withHeaders($inertia)
            ->post(route('profile.update'), ['_method' => 'put', 'phone' => '034 55 555 55', 'email' => '', 'photo' => $this->pngUpload(), 'remove_photo' => '0'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('profile.show'))
            ->assertSessionHas('status', 'Votre profil a été mis à jour.');

        $user->refresh();
        $this->assertSame('034 55 555 55', $user->phone);
        $this->assertNull($user->email);
        Storage::disk('local')->assertExists($user->photo_path);

        // Suppression de la photo
        $path = $user->photo_path;
        $this->withHeaders($inertia)->post(route('profile.update'), ['_method' => 'put', 'remove_photo' => '1'])->assertStatus(409);
        $this->assertNull($user->fresh()->photo_path);
        Storage::disk('local')->assertMissing($path);

        // Erreur : retour au formulaire avec le message
        $this->from(route('profile.edit'))->withHeaders($inertia)
            ->post(route('profile.update'), ['_method' => 'put', 'phone' => 'abc<script>'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('phone');
    }

    public function test_user_cannot_change_department_role_or_job_title(): void
    {
        $user = User::factory()->withRole(Role::STAFF, 'COLLECTE')->create(['job_title' => 'Agent']);
        $before = $user->only(['department_id', 'role_id', 'job_title', 'username']);

        $this->actingAs($user)->put(route('profile.update'), [
            'phone' => '0340000000',
            'department_id' => 1,
            'role_id' => Role::query()->firstWhere('name', Role::ADMIN)->id,
            'job_title' => 'Directeur',
            'username' => 'boss',
        ])->assertRedirect();

        $this->assertSame($before, $user->fresh()->only(['department_id', 'role_id', 'job_title', 'username']));
    }

    public function test_photo_must_be_an_image(): void
    {
        $user = User::factory()->withRole(Role::STAFF)->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'photo' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_theme_preference_is_remembered(): void
    {
        $user = User::factory()->withRole(Role::STAFF)->create();

        $this->actingAs($user)->patchJson(route('profile.theme'), ['theme' => 'dark'])->assertNoContent();
        $this->assertSame('dark', $user->fresh()->theme);

        $this->get(route('dashboard'))->assertSee('var theme = "dark"', false);

        $this->patchJson(route('profile.theme'), ['theme' => 'violet'])->assertUnprocessable();
    }

    /**
     * Vraie image PNG 1×1 (l'extension GD n'est pas disponible pour fake()->image()).
     */
    private function pngUpload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'photo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='),
        );
    }
}
