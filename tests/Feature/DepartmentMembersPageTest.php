<?php

namespace Tests\Feature;

use App\Models\DossierRecipient;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class DepartmentMembersPageTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_head_sees_workload_and_follow_up_of_the_team(): void
    {
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE');
        $late = $this->makeUser(Role::STAFF, 'TECHNIQUE', ['first_name' => 'Hery', 'last_name' => 'Rabe', 'phone' => '0349998877']);
        $this->makeUser(Role::STAFF, 'TECHNIQUE', ['is_active' => false]);
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');

        $dossier = $this->sendInternalRequest($sender, [$late]);
        DossierRecipient::query()->where('dossier_id', $dossier->id)->update(['due_at' => now()->subDay()]);

        $response = $this->actingAs($head)->get(route('department-members.index'))
            ->assertOk()
            ->assertSee('Rabe Hery')
            ->assertSee('Hery Rabe a 1 traitement(s) en retard')
            ->assertDontSee('0349998877');

        $summary = $response->viewData('summary');
        $this->assertSame(3, $summary['members']);
        $this->assertSame(2, $summary['active']);
        $this->assertSame(1, $summary['open']);
        $this->assertSame(1, $summary['overdue']);

        $member = $response->viewData('members')->getCollection()->firstWhere('id', $late->id);
        $this->assertSame(1, $member->overdue_count);
    }
}
