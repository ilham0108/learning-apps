<?php

namespace Tests\Feature\Candidates;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\Department;
use App\Domain\Candidates\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_manage_departments_and_positions()
    {
        $this->actingAs(User::factory()->hr()->create());

        $this->get(route('admin.positions.index'))->assertOk();

        $this->post(route('admin.departments.store'), ['name' => 'Finance'])->assertSessionHasNoErrors();
        $department = Department::query()->where('name', 'Finance')->sole();

        $this->post(route('admin.positions.store'), ['department' => $department->public_id, 'name' => 'Akuntan'])
            ->assertSessionHasNoErrors();
        $position = $department->positions()->sole();

        $this->patch(route('admin.positions.update', $position), ['name' => 'Akuntan Senior', 'is_active' => false])
            ->assertSessionHasNoErrors();

        $position->refresh();
        $this->assertSame('Akuntan Senior', $position->name);
        $this->assertFalse($position->is_active);
    }

    public function test_names_must_be_unique()
    {
        $this->actingAs(User::factory()->hr()->create());
        $position = Position::factory()->create(['name' => 'Kasir']);

        $this->post(route('admin.departments.store'), ['name' => $position->department->name])
            ->assertSessionHasErrors('name');
        $this->post(route('admin.positions.store'), ['department' => $position->department->public_id, 'name' => 'Kasir'])
            ->assertSessionHasErrors('name');

        // Nama posisi yang sama boleh di departemen lain.
        $this->post(route('admin.positions.store'), ['department' => Department::factory()->create()->public_id, 'name' => 'Kasir'])
            ->assertSessionHasNoErrors();
    }

    public function test_positions_require_a_valid_department_public_id()
    {
        $this->actingAs(User::factory()->hr()->create());
        $department = Department::factory()->create();

        $this->post(route('admin.positions.store'), ['department' => (string) $department->id, 'name' => 'Staf'])
            ->assertSessionHasErrors('department');
    }

    public function test_candidates_cannot_access_master_data()
    {
        $this->actingAs(CandidateProfile::factory()->activated()->create()->user)
            ->get(route('admin.positions.index'))
            ->assertNotFound();
    }
}
