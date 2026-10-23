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
            ->assertInertia(fn ($page) => $page->component('DepartmentMembers/Index')
                ->where('attention.name', 'Hery Rabe')
                ->where('attention.overdue', 1))
            ->assertPropsSee('Rabe Hery')
            ->assertPropsDontSee('0349998877');
        $this->assertStringContainsString('{attention.name} a {attention.overdue} traitement(s) en retard', (string) file_get_contents(resource_path('js/pages/DepartmentMembers/Index.jsx')));

        $summary = $response->inertiaProps('summary');
        $this->assertSame(3, $summary['members']);
        $this->assertSame(2, $summary['active']);
        $this->assertSame(1, $summary['open']);
        $this->assertSame(1, $summary['overdue']);

        $member = collect($response->inertiaProps('members.data'))->firstWhere('id', $late->id);
        $this->assertSame(1, $member['overdue']);
        $this->assertFalse(view()->exists('department-members.index'));
    }
}
