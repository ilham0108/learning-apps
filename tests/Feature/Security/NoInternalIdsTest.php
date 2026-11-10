<?php

namespace Tests\Feature\Security;

use App\Domain\Access\Actions\CreateActivationLink;
use App\Domain\Candidates\Models\CandidateEvaluation;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Domain\QuestionBank\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Concerns\AssertsNoInternalIds;
use Tests\TestCase;

/**
 * Props halaman utama tidak boleh memuat primary key atau foreign key internal.
 * Setiap halaman baru menambahkan kasusnya di sini.
 */
class NoInternalIdsTest extends TestCase
{
    use AssertsNoInternalIds, RefreshDatabase;

    public function test_admin_pages_expose_only_public_ids()
    {
        $hr = User::factory()->hr()->create();
        $application = RecruitmentApplication::factory()->for(RecruitmentCampaign::factory()->assignedTo($hr), 'campaign')->create();
        $application->internalNotes()->create(['author_id' => $hr->id, 'body' => 'Catatan']);
        CandidateEvaluation::factory()->finalized()->create([
            'recruitment_application_id' => $application->id,
            'evaluator_id' => $hr->id,
        ]);
        $application->stageHistories()->create(['to_stage' => 'invited', 'changed_by' => $hr->id]);

        $urls = [
            route('admin.dashboard'),
            route('profile.edit'),
            route('admin.positions.index'),
            route('admin.campaigns.index'),
            route('admin.campaigns.show', $application->campaign),
            route('admin.candidates.index'),
            route('admin.candidates.show', $application->candidateProfile),
        ];

        foreach ($urls as $url) {
            $response = $this->actingAs($hr)->get($url)->assertOk();
            $this->assertResponseHasNoInternalIds($response);
        }

        $admin = User::factory()->superAdmin()->create();
        User::factory()->hr()->create();

        foreach ([route('admin.dashboard'), route('admin.campaigns.show', $application->campaign)] as $url) {
            $this->assertResponseHasNoInternalIds($this->actingAs($admin)->get($url)->assertOk());
        }
    }

    public function test_candidate_pages_expose_only_public_ids()
    {
        $profile = CandidateProfile::factory()->activated()->create();
        RecruitmentApplication::factory()->count(2)->for($profile)->create();

        $response = $this->actingAs($profile->user)->get(route('dashboard'))->assertOk();
        $this->assertResponseHasNoInternalIds($response);
        $this->assertSame($profile->user->public_id, $response->inertiaProps('auth.user.id'));
    }

    public function test_learning_pages_expose_only_public_ids()
    {
        $hr = User::factory()->hr()->create();
        $application = RecruitmentApplication::factory()
            ->for(CandidateProfile::factory()->activated(), 'candidateProfile')
            ->for(RecruitmentCampaign::factory()->assignedTo($hr), 'campaign')
            ->create();
        $module = LearningModule::factory()->for(LearningProgram::factory()->published(), 'program')->create();
        $program = $module->program;
        $article = LearningMaterial::factory()->for($module, 'module')->create();
        $pdf = LearningMaterial::factory()->for($module, 'module')->pdf()->create();
        ProgramAssignment::factory()->create([
            'learning_program_id' => $program->id,
            'recruitment_application_id' => $application->id,
        ]);

        foreach ([
            route('admin.programs.index'),
            route('admin.programs.show', $program),
            route('admin.materials.create', $module),
            route('admin.materials.edit', $pdf),
            route('admin.candidates.show', $application->candidateProfile),
        ] as $url) {
            $this->assertResponseHasNoInternalIds($this->actingAs($hr)->get($url)->assertOk());
        }

        $candidate = $application->candidateProfile->user;

        foreach ([
            route('learn.index'),
            route('learn.show', $program),
            route('learn.material', [$program, $article]),
            route('learn.material', [$program, $pdf]),
        ] as $url) {
            $this->assertResponseHasNoInternalIds($this->actingAs($candidate)->get($url)->assertOk());
        }
    }

    public function test_question_bank_and_exam_pages_expose_only_public_ids()
    {
        $hr = User::factory()->hr()->create();
        $case = Question::factory()->caseStudy()->create();
        $mc = Question::factory()->multipleChoice()->create();
        $exam = Exam::factory()->published()->withQuestions([$case, $mc])->create();
        $exam->prerequisitePrograms()->attach(LearningProgram::factory()->create()->id);
        ExamAssignment::factory()->for($exam)->create([
            'recruitment_application_id' => RecruitmentApplication::factory()
                ->for(RecruitmentCampaign::factory()->assignedTo($hr), 'campaign')
                ->create()->id,
        ]);
        Question::factory()->create();

        foreach ([
            route('admin.question-categories.index'),
            route('admin.questions.index'),
            route('admin.questions.create'),
            route('admin.questions.edit', $case),
            route('admin.questions.edit', $mc),
            route('admin.exams.index'),
            route('admin.exams.create'),
            route('admin.exams.show', $exam),
            route('admin.exams.edit', $exam),
        ] as $url) {
            $this->assertResponseHasNoInternalIds($this->actingAs($hr)->get($url)->assertOk());
        }

        $draft = Exam::factory()->create();
        $this->assertResponseHasNoInternalIds(
            $this->actingAs($hr)->getJson(route('admin.exams.question-picker', $draft))->assertOk(),
        );
    }

    public function test_auth_pages_expose_only_public_ids()
    {
        $profile = CandidateProfile::factory()->create();
        $this->actingAs(User::factory()->hr()->create());
        $url = app(CreateActivationLink::class)($profile->user);
        auth()->logout();

        $this->assertResponseHasNoInternalIds($this->get($url)->assertOk());
        $this->assertResponseHasNoInternalIds(
            $this->actingAs($profile->user)->get(route('consent.show'))->assertOk(),
        );
    }

    public function test_the_helper_detects_internal_ids()
    {
        $failures = 0;

        foreach ([['id' => 5], ['items' => [['user_id' => 'x']]]] as $payload) {
            try {
                $this->assertNoInternalIds($payload);
            } catch (AssertionFailedError) {
                $failures++;
            }
        }

        $this->assertSame(2, $failures);
        $this->assertNoInternalIds(['id' => '01jc8x', 'public_id' => '01jc8x']);
    }
}
