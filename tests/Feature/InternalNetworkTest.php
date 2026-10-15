<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalNetworkTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_from_outside_the_internal_networks_are_refused(): void
    {
        config(['app.allowed_networks' => '192.168.1.0/24,127.0.0.1']);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.25'])->get(route('login'))->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '41.188.10.20'])->get(route('login'))
            ->assertForbidden()
            ->assertSee('Accès réservé au réseau interne');
    }

    public function test_no_filtering_when_no_network_is_configured(): void
    {
        config(['app.allowed_networks' => '']);

        $this->withServerVariables(['REMOTE_ADDR' => '41.188.10.20'])->get(route('login'))->assertOk();
    }
}
