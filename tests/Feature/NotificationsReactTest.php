<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Page « Notifications » en React : notifications de l'utilisateur
 * connecté, ouverture par notifications.open, « Tout marquer comme lu ».
 */
class NotificationsReactTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_the_page_lists_the_users_notifications(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $notification = $recipient->notifications()->sole();

        $this->actingAs($recipient)->get(route('notifications.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->where('title', 'Notifications')
            ->where('hasUnread', true)
            ->has('notifications.data', 1)
            ->where('notifications.data.0.url', route('notifications.open', $notification->id))
            ->where('notifications.data.0.unread', true)
            ->where('notifications.data.0.reference', $dossier->reference)
            ->where('notifications.data.0.type', 'Nouveau dossier')
            ->where('urls.readAll', route('notifications.read-all')));

        // L'expéditeur ne voit pas les notifications des autres.
        $this->actingAs($sender)->get(route('notifications.index'))->assertInertia(fn (Assert $page) => $page->has('notifications.data', 0)->where('hasUnread', false));

        $this->assertFalse(view()->exists('notifications.index'));
    }

    public function test_mark_all_as_read_reloads_the_page_with_a_message(): void
    {
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $this->sendInternalRequest($this->makeUser(Role::STAFF, 'PRODUCTION'), [$recipient]);

        $this->actingAs($recipient)->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('notifications.read-all'))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('notifications.index'))
            ->assertSessionHas('status', 'Toutes les notifications ont été marquées comme lues.');

        $this->assertSame(0, $recipient->unreadNotifications()->count());
        $this->flushHeaders()->get(route('notifications.index'))->assertInertia(fn (Assert $page) => $page->where('hasUnread', false)->where('notifications.data.0.unread', false));
    }
}
