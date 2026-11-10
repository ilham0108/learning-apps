<?php

namespace Tests\Feature\Learning;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\MaterialProgress;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Domain\Learning\Queries\ProgramProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CandidateLearningTest extends TestCase
{
    use RefreshDatabase;

    private CandidateProfile $profile;

    private LearningProgram $program;

    /** @var list<LearningMaterial> */
    private array $materials;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = CandidateProfile::factory()->activated()->create();
        $application = RecruitmentApplication::factory()->for($this->profile)->create();

        $this->program = LearningProgram::factory()->published()->create();
        $module = LearningModule::factory()->for($this->program, 'program')->create(['position' => 1]);
        $this->materials = [
            LearningMaterial::factory()->for($module, 'module')->create(['position' => 1]),
            LearningMaterial::factory()->for($module, 'module')->create(['position' => 2]),
            LearningMaterial::factory()->for($module, 'module')->optional()->create(['position' => 3]),
        ];

        ProgramAssignment::factory()->create([
            'learning_program_id' => $this->program->id,
            'recruitment_application_id' => $application->id,
        ]);

        $this->actingAs($this->profile->user);
    }

    public function test_candidates_only_see_assigned_published_programs()
    {
        LearningProgram::factory()->published()->create();
        $draft = LearningProgram::factory()->create();
        ProgramAssignment::factory()->create([
            'learning_program_id' => $draft->id,
            'recruitment_application_id' => $this->profile->applications()->first()->id,
        ]);

        $this->get(route('learn.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('programs', 1)
                ->where('programs.0.id', $this->program->public_id)
                ->where('programs.0.progress.status', 'not_started'));

        $this->get(route('learn.show', $draft))->assertNotFound();
        $this->get(route('learn.show', LearningProgram::factory()->published()->create()))->assertNotFound();
    }

    public function test_opening_a_material_is_recorded_but_does_not_complete_it()
    {
        $material = $this->materials[0];

        $this->get(route('learn.material', [$this->program, $material]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('material.completed_at', null)
                ->where('previous', null)
                ->where('next.id', $this->materials[1]->public_id));

        $progress = MaterialProgress::query()->sole();
        $this->assertNotNull($progress->first_opened_at);
        $this->assertNull($progress->completed_at);

        $this->get(route('learn.show', $this->program))
            ->assertInertia(fn (Assert $page) => $page
                ->where('program.progress.status', 'in_progress')
                ->where('program.progress.percent', 0)
                ->where('program.modules.0.materials.0.status', 'in_progress'));
    }

    public function test_marking_required_materials_complete_updates_progress()
    {
        foreach (array_slice($this->materials, 0, 2) as $material) {
            $this->post(route('learn.material.complete', [$this->program, $material]))->assertSessionHasNoErrors();
        }

        // Materi opsional tidak memengaruhi persentase.
        $this->get(route('learn.show', $this->program))
            ->assertInertia(fn (Assert $page) => $page
                ->where('program.progress.percent', 100)
                ->where('program.progress.status', 'completed'));

        // Menandai ulang bersifat idempoten.
        $completedAt = MaterialProgress::query()->first()->completed_at;
        $this->travel(1)->hour();
        $this->post(route('learn.material.complete', [$this->program, $this->materials[0]]));
        $this->assertEquals($completedAt, MaterialProgress::query()->first()->completed_at);
    }

    public function test_new_required_material_recalculates_progress_without_losing_history()
    {
        $this->post(route('learn.material.complete', [$this->program, $this->materials[0]]));
        $this->post(route('learn.material.complete', [$this->program, $this->materials[1]]));

        LearningMaterial::factory()->for($this->materials[0]->module, 'module')->create(['position' => 4]);

        $summary = app(ProgramProgress::class)->forCandidate($this->profile->id, [$this->program->id])[$this->program->id];
        $this->assertSame(66, $summary->percent());
        $this->assertSame('in_progress', $summary->status()->value);
        $this->assertSame(2, MaterialProgress::query()->whereNotNull('completed_at')->count());
    }

    public function test_archived_materials_and_mismatched_urls_are_not_accessible()
    {
        $this->materials[1]->forceFill(['archived_at' => now()])->save();
        $this->get(route('learn.material', [$this->program, $this->materials[1]]))->assertNotFound();

        $otherProgram = LearningProgram::factory()->published()->create();
        $this->get(route('learn.material', [$otherProgram, $this->materials[0]]))->assertNotFound();

        $foreign = LearningMaterial::factory()->create();
        $this->get(route('learn.material', [$this->program, $foreign]))->assertNotFound();
        $this->post(route('learn.material.complete', [$this->program, $foreign]))->assertNotFound();
    }

    public function test_material_page_never_exposes_files_without_signed_urls()
    {
        $pdf = LearningMaterial::factory()->for($this->materials[0]->module, 'module')->pdf()->create(['position' => 5]);

        $this->get(route('learn.material', [$this->program, $pdf]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('material.file_url', fn (string $url) => str_contains($url, 'signature='))
                ->missing('material.file_path'));
    }

    public function test_staff_cannot_use_candidate_learning_pages()
    {
        $this->actingAs(User::factory()->hr()->create())->get(route('learn.index'))->assertNotFound();
    }
}
