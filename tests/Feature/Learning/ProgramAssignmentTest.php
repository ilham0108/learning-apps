<?php

namespace Tests\Feature\Learning;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Domain\Shared\Enums\AssignmentSource;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private RecruitmentCampaign $campaign;

    private LearningProgram $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->hr()->create();
        $this->campaign = RecruitmentCampaign::factory()->assignedTo($this->hr)->create();
        $this->program = LearningProgram::factory()->published()->create();
        $this->actingAs($this->hr);
    }

    public function test_assigning_to_a_campaign_creates_one_row_per_active_application()
    {
        RecruitmentApplication::factory()->count(3)->for($this->campaign, 'campaign')->create(['stage' => RecruitmentStage::Registered]);
        RecruitmentApplication::factory()->for($this->campaign, 'campaign')->create(['stage' => RecruitmentStage::Rejected]);
        RecruitmentApplication::factory()->create(['stage' => RecruitmentStage::Registered]);

        $this->post(route('admin.programs.assignments.store', $this->program), [
            'via' => 'campaign',
            'campaign' => $this->campaign->public_id,
            'due_at' => now()->addWeek()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, ProgramAssignment::query()->count());
        $this->assertSame(AssignmentSource::Campaign, ProgramAssignment::query()->first()->assigned_via);
        $this->assertNotNull(ProgramAssignment::query()->first()->due_at);
        $this->assertEquals(['via' => 'campaign', 'count' => 3], ActivityLog::query()->where('action', 'program.assigned')->sole()->properties);

        // Menugaskan ulang tidak menggandakan baris.
        $this->post(route('admin.programs.assignments.store', $this->program), ['via' => 'campaign', 'campaign' => $this->campaign->public_id]);
        $this->assertSame(3, ProgramAssignment::query()->count());
    }

    public function test_assigning_to_a_position_covers_open_campaigns_in_scope()
    {
        $position = $this->campaign->position;
        RecruitmentApplication::factory()->for($this->campaign, 'campaign')->create(['stage' => RecruitmentStage::Registered]);
        $closed = RecruitmentCampaign::factory()->closed()->for($position)->assignedTo($this->hr)->create();
        RecruitmentApplication::factory()->for($closed, 'campaign')->create(['stage' => RecruitmentStage::Registered]);
        $otherHr = RecruitmentCampaign::factory()->for($position)->create();
        RecruitmentApplication::factory()->for($otherHr, 'campaign')->create(['stage' => RecruitmentStage::Registered]);

        $this->post(route('admin.programs.assignments.store', $this->program), [
            'via' => 'position',
            'position' => $position->public_id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ProgramAssignment::query()->count());
    }

    public function test_selection_only_accepts_applications_in_scope()
    {
        $mine = RecruitmentApplication::factory()->for($this->campaign, 'campaign')->create(['stage' => RecruitmentStage::Registered]);
        $other = RecruitmentApplication::factory()->create(['stage' => RecruitmentStage::Registered]);

        $this->post(route('admin.programs.assignments.store', $this->program), [
            'via' => 'selection',
            'applications' => [$mine->public_id, $other->public_id],
        ])->assertSessionHasErrors('applications.1');

        $this->post(route('admin.programs.assignments.store', $this->program), [
            'via' => 'individual',
            'applications' => [$mine->public_id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$mine->id], ProgramAssignment::query()->pluck('recruitment_application_id')->all());
    }

    public function test_only_published_programs_can_be_assigned()
    {
        $draft = LearningProgram::factory()->create();

        $this->post(route('admin.programs.assignments.store', $draft), [
            'via' => 'campaign',
            'campaign' => $this->campaign->public_id,
        ])->assertForbidden();
    }

    public function test_assignments_can_be_revoked_within_scope()
    {
        $assignment = ProgramAssignment::factory()->create([
            'learning_program_id' => $this->program->id,
            'recruitment_application_id' => RecruitmentApplication::factory()->for($this->campaign, 'campaign')->create()->id,
        ]);
        $foreign = ProgramAssignment::factory()->create(['learning_program_id' => $this->program->id]);

        $this->delete(route('admin.program-assignments.destroy', $foreign))->assertNotFound();
        $this->delete(route('admin.program-assignments.destroy', $assignment))->assertSessionHasNoErrors();

        $this->assertModelMissing($assignment);
        $this->assertModelExists($foreign);
    }

    public function test_program_page_only_lists_assignments_in_scope()
    {
        ProgramAssignment::factory()->create([
            'learning_program_id' => $this->program->id,
            'recruitment_application_id' => RecruitmentApplication::factory()->for($this->campaign, 'campaign')->create()->id,
        ]);
        ProgramAssignment::factory()->create(['learning_program_id' => $this->program->id]);

        $this->get(route('admin.programs.show', $this->program))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('assignments', 1)->where('assignments.0.progress.status', 'not_started'));
    }
}
