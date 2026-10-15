<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Dossier;
use App\Models\Role;
use App\Services\DossierTrends;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class DashboardTrendsTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_department_head_sees_only_the_curve_of_their_department(): void
    {
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'COLLECTE');
        $collecte = Department::query()->firstWhere('code', 'COLLECTE');
        $rse = Department::query()->firstWhere('code', 'RSE');

        Dossier::factory()->create(['originating_department_id' => $collecte->id, 'created_at' => now()]);
        Dossier::factory()->create(['originating_department_id' => $collecte->id, 'created_at' => now()->subDays(2)]);
        Dossier::factory()->create(['originating_department_id' => $collecte->id, 'created_at' => now()->subMonths(3)]);
        Dossier::factory()->create(['originating_department_id' => $rse->id, 'created_at' => now()]);

        $trends = app(DossierTrends::class)->for($head);

        $week = $trends['periods']['week'];
        $this->assertCount(7, $week['labels']);
        $this->assertCount(1, $week['series']);
        $this->assertSame('Collecte', $week['series'][0]['name']);
        $this->assertSame(DossierTrends::COLORS['COLLECTE'], $week['series'][0]['color']);
        $this->assertSame(2, $week['series'][0]['total']);
        $this->assertSame(1, $week['series'][0]['data'][6]);

        $year = $trends['periods']['year'];
        $this->assertCount(12, $year['labels']);
        $this->assertSame(3, $year['series'][0]['total']);
    }

    public function test_global_statistics_show_one_coloured_curve_per_department(): void
    {
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('data-testid="trend-chart"', false);

        $series = collect(app(DossierTrends::class)->for($admin)['periods']['month']['series']);
        $this->assertSame(Department::query()->active()->count(), $series->count());
        $this->assertSame(DossierTrends::COLORS['RSE'], $series->firstWhere('name', 'RSE')['color']);
        $this->assertCount(30, app(DossierTrends::class)->for($admin)['periods']['month']['labels']);
    }

    public function test_employees_without_statistics_rights_get_no_department_chart(): void
    {
        $this->actingAs($this->makeUser(Role::STAFF, 'PRODUCTION'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="trend-chart"', false);
    }

    public function test_every_user_gets_charts_of_their_own_activity(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $this->sendInternalRequest($sender, [$recipient]);
        $this->sendInternalRequest($recipient, [$sender]);
        $this->sendInternalRequest($sender, [$recipient]);

        $week = collect(app(DossierTrends::class)->personal($sender)['periods']['week']['series'])->keyBy('name');
        $this->assertSame(1, $week['Reçus']['total']);
        $this->assertSame(2, $week['Envoyés']['total']);

        $response = $this->actingAs($recipient)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-testid="personal-chart"', false)
            ->assertSee('Mes traitements');

        $this->assertSame(2, array_sum($response->viewData('myTreatments')->all()));
    }
}
