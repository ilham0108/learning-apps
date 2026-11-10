<?php

namespace Tests\Feature\Exams;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->hr()->create();
        $this->actingAs($this->hr);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Tes Produk',
            'type' => 'product_knowledge',
            'duration_minutes' => 30,
            'passing_grade' => 70,
            'max_attempts' => 2,
            'opens_at' => '2026-11-01T08:00',
            'closes_at' => '2026-11-15T17:00',
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'navigation' => 'free',
            'score_policy' => 'highest',
            'multi_select_scoring' => 'all_or_nothing',
            'result_release' => 'immediate',
            'show_review' => false,
            'status' => 'draft',
            'prerequisite_programs' => [],
            'related_programs' => [],
            ...$overrides,
        ];
    }

    public function test_exams_are_created_as_draft_with_times_stored_in_utc()
    {
        $program = LearningProgram::factory()->published()->create();

        $this->post(route('admin.exams.store'), $this->payload(['prerequisite_programs' => [$program->public_id]]))
            ->assertSessionHasNoErrors();

        $exam = Exam::query()->sole();
        $this->assertSame(ExamStatus::Draft, $exam->status);
        // 08:00 WIB = 01:00 UTC.
        $this->assertSame('2026-11-01 01:00:00', $exam->opens_at->format('Y-m-d H:i:s'));
        $this->assertTrue($exam->prerequisitePrograms()->whereKey($program->id)->exists());

        $this->get(route('admin.exams.edit', $exam))->assertInertia(fn (Assert $page) => $page
            ->where('exam.opens_at_input', '2026-11-01T08:00'));
    }

    public function test_exam_validation()
    {
        $this->post(route('admin.exams.store'), $this->payload(['closes_at' => '2026-10-01T08:00']))->assertSessionHasErrors('closes_at');
        $this->post(route('admin.exams.store'), $this->payload(['passing_grade' => 101]))->assertSessionHasErrors('passing_grade');
        $this->post(route('admin.exams.store'), $this->payload(['max_attempts' => 0]))->assertSessionHasErrors('max_attempts');
        $this->post(route('admin.exams.store'), $this->payload(['status' => 'published']))->assertSessionHasErrors('status');
    }

    public function test_questions_can_be_added_weighted_reordered_and_removed()
    {
        $exam = Exam::factory()->create();
        $mc = Question::factory()->multipleChoice()->create(['default_points' => 5]);
        $case = Question::factory()->caseStudy()->create();
        $archived = Question::factory()->archived()->create();

        $this->post(route('admin.exams.questions.store', $exam), [
            'questions' => [$mc->public_id, $case->public_id, $archived->public_id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$mc->id, $case->id], $exam->examQuestions()->pluck('question_id')->all());
        // Bobot studi kasus = jumlah bobot sub-soal (10 + 10).
        $this->assertSame(['5.00', '20.00'], $exam->examQuestions()->pluck('points')->all());

        $this->patch(route('admin.exams.questions.update', [$exam, $mc]), ['points' => 7.5])->assertSessionHasNoErrors();
        $this->patch(route('admin.exams.questions.update', [$exam, $case]), ['points' => 3])->assertSessionHasErrors('points');
        $this->post(route('admin.exams.questions.move', [$exam, $case]), ['direction' => 'up'])->assertSessionHasNoErrors();

        $this->assertSame([$case->id, $mc->id], $exam->examQuestions()->pluck('question_id')->all());
        $this->assertSame('7.50', $exam->examQuestions()->where('question_id', $mc->id)->value('points'));

        $this->delete(route('admin.exams.questions.destroy', [$exam, $case]))->assertSessionHasNoErrors();
        $this->assertSame([[$mc->id, 1]], $exam->examQuestions()->get()->map(fn ($row) => [$row->question_id, $row->position])->all());
    }

    public function test_locked_exams_keep_their_questions_but_allow_schedule_changes()
    {
        $question = Question::factory()->create();
        $exam = Exam::factory()->locked()->withQuestions([$question])->create();

        $this->post(route('admin.exams.questions.store', $exam), ['questions' => [Question::factory()->create()->public_id]])->assertForbidden();
        $this->patch(route('admin.exams.questions.update', [$exam, $question]), ['points' => 1])->assertForbidden();
        $this->delete(route('admin.exams.questions.destroy', [$exam, $question]))->assertForbidden();

        $this->put(route('admin.exams.update', $exam), $this->payload(['title' => 'Judul baru', 'status' => 'published']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Judul baru', $exam->refresh()->title);

        $this->put(route('admin.exams.update', $exam), $this->payload(['status' => 'draft']))->assertSessionHasErrors('status');
        $this->assertSame(1, $exam->examQuestions()->count());
    }

    public function test_publishing_requires_questions_and_is_audited()
    {
        $exam = Exam::factory()->withQuestions([Question::factory()->create()])->create();

        $this->put(route('admin.exams.update', $exam), $this->payload(['status' => 'published']))->assertSessionHasNoErrors();

        $this->assertSame(ExamStatus::Published, $exam->refresh()->status);
        $this->assertTrue(ActivityLog::query()->where('action', 'exam.status_changed')->exists());
    }

    public function test_duplicating_creates_a_draft_with_latest_question_versions()
    {
        $old = Question::factory()->create();
        $new = Question::factory()->create(['previous_version_id' => $old->id, 'version' => 2]);
        $old->update(['superseded_at' => now()]);
        $source = Exam::factory()->locked()->withQuestions([$old])->create();
        ExamAssignment::factory()->for($source)->create();

        $this->post(route('admin.exams.duplicate', $source))->assertSessionHasNoErrors();

        $copy = Exam::query()->whereKeyNot($source->id)->sole();
        $this->assertSame(ExamStatus::Draft, $copy->status);
        $this->assertNull($copy->locked_at);
        $this->assertSame($source->id, $copy->duplicated_from_id);
        $this->assertSame([$new->id], $copy->examQuestions()->pluck('question_id')->all());
        $this->assertSame(0, $copy->assignments()->count());
    }

    public function test_question_picker_returns_eligible_questions_without_answer_keys()
    {
        $exam = Exam::factory()->create();
        $inExam = Question::factory()->create();
        $exam->examQuestions()->create(['question_id' => $inExam->id, 'points' => 10, 'position' => 1]);
        $eligible = Question::factory()->create();
        Question::factory()->archived()->create();

        $response = $this->getJson(route('admin.exams.question-picker', $exam))->assertOk();

        $this->assertSame([$eligible->public_id], array_column($response->json('data'), 'id'));
        $this->assertStringNotContainsString('is_correct', $response->getContent());
    }

    public function test_assignment_with_individual_schedule_override()
    {
        $campaign = RecruitmentCampaign::factory()->assignedTo($this->hr)->create();
        RecruitmentApplication::factory()->count(2)->for($campaign, 'campaign')->create(['stage' => RecruitmentStage::Registered]);
        $draft = Exam::factory()->create();
        $exam = Exam::factory()->published()->withQuestions([Question::factory()->create()])->create();

        $this->post(route('admin.exams.assignments.store', $draft), ['via' => 'campaign', 'campaign' => $campaign->public_id])->assertForbidden();
        $this->post(route('admin.exams.assignments.store', $exam), ['via' => 'campaign', 'campaign' => $campaign->public_id])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $exam->assignments()->count());

        $assignment = $exam->assignments()->first();
        $this->patch(route('admin.exam-assignments.update', $assignment), [
            'closes_at_override' => '2026-12-01T17:00',
            'max_attempts_override' => 3,
        ])->assertSessionHasNoErrors();

        $assignment->refresh();
        $this->assertSame('2026-12-01 10:00:00', $assignment->closes_at_override->format('Y-m-d H:i:s'));
        $this->assertSame(3, $assignment->effectiveMaxAttempts());
        $this->assertTrue(ActivityLog::query()->where('action', 'exam_assignment.schedule_changed')->exists());
    }

    public function test_assignments_outside_scope_or_with_attempts_cannot_be_revoked()
    {
        $exam = Exam::factory()->published()->create();
        $foreign = ExamAssignment::factory()->for($exam)->create();
        $mine = ExamAssignment::factory()->for($exam)->create([
            'recruitment_application_id' => RecruitmentApplication::factory()
                ->for(RecruitmentCampaign::factory()->assignedTo($this->hr), 'campaign')
                ->create()->id,
        ]);
        ExamAttempt::factory()->for($mine, 'assignment')->submitted()->create();

        $this->delete(route('admin.exam-assignments.destroy', $foreign))->assertNotFound();
        $this->delete(route('admin.exam-assignments.destroy', $mine));
        $this->assertModelExists($mine);
    }

    public function test_exam_page_lists_only_assignments_in_scope()
    {
        $exam = Exam::factory()->published()->create();
        ExamAssignment::factory()->for($exam)->create();
        ExamAssignment::factory()->for($exam)->create([
            'recruitment_application_id' => RecruitmentApplication::factory()
                ->for(RecruitmentCampaign::factory()->assignedTo($this->hr), 'campaign')
                ->create()->id,
        ]);

        $this->get(route('admin.exams.show', $exam))->assertInertia(fn (Assert $page) => $page->has('assignments', 1));
    }
}
