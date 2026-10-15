<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Jean')
            ->assertSee('Rakoto')
            ->assertSee('Électricien')
            ->assertSee('Technique')
            ->assertSee('Employé')
            ->assertSee('034 00 000 00')
            ->assertSee('Déconnexion');
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
