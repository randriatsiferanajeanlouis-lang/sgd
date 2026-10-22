<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manifest_describes_an_installable_application(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('SOGEDIPROMA', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/dashboard', $manifest['start_url']);

        $sizes = collect($manifest['icons'])->pluck('sizes')->all();
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertTrue(collect($manifest['icons'])->contains('purpose', 'maskable'));

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            [$width, $height] = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($icon['sizes'], "{$width}x{$height}");
        }

        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
    }

    public function test_pages_link_the_manifest_and_show_the_install_button(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('data-testid="pwa-install"', false)
            ->assertSee('Installer l’application');

        $this->actingAs(User::factory()->withRole(Role::STAFF)->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('data-testid="pwa-install"', false);
    }

    public function test_service_worker_never_caches_private_pages(): void
    {
        $worker = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("event.request.mode !== 'navigate'", $worker);
        $this->assertStringNotContainsString('cache.put', $worker, 'Aucune page privée n’est mise en cache.');
    }
}
