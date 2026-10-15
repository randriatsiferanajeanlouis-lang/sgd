<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_login_page_asks_for_username_and_offers_no_registration(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('Créer un compte');

        $this->get('/register')->assertNotFound();
        $this->post('/register', ['username' => 'pirate'])->assertNotFound();
    }

    public function test_user_can_log_in_with_username(): void
    {
        $user = $this->employee(['username' => 'marie']);

        $this->post(route('login.store'), ['username' => ' Marie ', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_unknown_username_is_rejected(): void
    {
        $this->post(route('login.store'), ['username' => 'inconnu', 'password' => 'password'])
            ->assertSessionHasErrors(['username' => 'Identifiant ou mot de passe incorrect.']);

        $this->assertGuest();
    }

    public function test_email_cannot_be_used_to_log_in(): void
    {
        $this->employee(['username' => 'marie', 'email' => 'marie@example.com']);

        $this->post(route('login.store'), ['username' => 'marie@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['username' => 'Identifiant ou mot de passe incorrect.']);

        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->employee(['username' => 'marie']);

        $this->post(route('login.store'), ['username' => 'marie', 'password' => 'mauvais'])
            ->assertSessionHasErrors(['username' => 'Identifiant ou mot de passe incorrect.']);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in_and_gets_a_clear_message(): void
    {
        $this->employee(['username' => 'marie', 'is_active' => false]);

        $this->post(route('login.store'), ['username' => 'marie', 'password' => 'password'])
            ->assertSessionHasErrors(['username' => __('auth.inactive')]);

        $this->assertGuest();
    }

    public function test_inactive_account_status_is_not_revealed_without_the_right_password(): void
    {
        $this->employee(['username' => 'marie', 'is_active' => false]);

        $this->post(route('login.store'), ['username' => 'marie', 'password' => 'mauvais'])
            ->assertSessionHasErrors(['username' => 'Identifiant ou mot de passe incorrect.']);
    }

    public function test_deactivated_user_loses_access_with_an_open_session(): void
    {
        $user = $this->employee();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['is_active' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $this->employee(['username' => 'marie']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['username' => 'marie', 'password' => 'mauvais']);
        }

        $this->post(route('login.store'), ['username' => 'marie', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_session_is_regenerated_on_login(): void
    {
        $this->employee(['username' => 'marie']);
        $this->startSession();
        $before = session()->getId();

        $this->post(route('login.store'), ['username' => 'marie', 'password' => 'password']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs($this->employee())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('profile.show'))->assertRedirect(route('login'));
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_logged_in_user_is_redirected_away_from_login(): void
    {
        $this->actingAs($this->employee())->get(route('login'))->assertRedirect(route('dashboard'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): User
    {
        return User::factory()->withRole(Role::STAFF, 'COLLECTE')->create($attributes);
    }
}
