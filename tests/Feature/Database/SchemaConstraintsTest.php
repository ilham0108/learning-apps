<?php

namespace Tests\Feature\Database;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Enums\AttemptStatus;
use App\Domain\Exams\Models\AttemptAnswer;
use App\Domain\Exams\Models\AttemptQuestion;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\MaterialProgress;
use App\Domain\QuestionBank\Models\Question;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_session_uses_utc()
    {
        $this->assertSame('+00:00', DB::selectOne('select @@session.time_zone as tz')->tz);
    }

    public function test_an_assignment_cannot_have_two_attempts_in_progress()
    {
        $assignment = ExamAssignment::factory()->create();
        ExamAttempt::factory()->for($assignment, 'assignment')->create(['attempt_number' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);

        ExamAttempt::factory()->for($assignment, 'assignment')->create(['attempt_number' => 2]);
    }

    public function test_a_new_attempt_can_start_after_the_previous_one_finished()
    {
        $assignment = ExamAssignment::factory()->create();
        $first = ExamAttempt::factory()->for($assignment, 'assignment')->create(['attempt_number' => 1]);

        $first->update(['status' => AttemptStatus::Submitted, 'submitted_at' => now()]);

        ExamAttempt::factory()->for($assignment, 'assignment')->create(['attempt_number' => 2]);
        ExamAttempt::query()->where('exam_assignment_id', $assignment->id)->update(['status' => AttemptStatus::AutoSubmitted]);
        ExamAttempt::factory()->for($assignment, 'assignment')->create(['attempt_number' => 3]);

        $this->assertSame(3, $assignment->attempts()->count());
        $this->assertSame(1, $assignment->attempts()->where('status', AttemptStatus::InProgress)->count());
    }

    public function test_attempt_numbers_are_unique_per_assignment()
    {
        $assignment = ExamAssignment::factory()->create();
        ExamAttempt::factory()->for($assignment, 'assignment')->submitted()->create(['attempt_number' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);

        ExamAttempt::factory()->for($assignment, 'assignment')->submitted()->create(['attempt_number' => 1]);
    }

    public function test_a_question_can_only_be_answered_once_per_attempt()
    {
        $question = AttemptQuestion::factory()->create();
        AttemptAnswer::factory()->create(['attempt_question_id' => $question->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        AttemptAnswer::factory()->create(['attempt_question_id' => $question->id]);
    }

    public function test_a_candidate_can_apply_to_a_campaign_only_once()
    {
        $profile = CandidateProfile::factory()->create();
        $campaign = RecruitmentCampaign::factory()->create();
        RecruitmentApplication::factory()->for($profile)->for($campaign, 'campaign')->create();

        $this->expectException(UniqueConstraintViolationException::class);

        RecruitmentApplication::factory()->for($profile)->for($campaign, 'campaign')->create();
    }

    public function test_a_candidate_can_apply_to_different_campaigns()
    {
        $profile = CandidateProfile::factory()->create();

        RecruitmentApplication::factory()->count(2)->for($profile)->create();

        $this->assertSame(2, $profile->applications()->count());
    }

    public function test_a_question_can_only_appear_once_per_exam()
    {
        $question = Question::factory()->create();
        $exam = Exam::factory()->withQuestions([$question])->create();

        $this->expectException(UniqueConstraintViolationException::class);

        $exam->examQuestions()->create(['question_id' => $question->id, 'points' => 5, 'position' => 2]);
    }

    public function test_an_exam_can_only_be_assigned_once_per_application()
    {
        $assignment = ExamAssignment::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        ExamAssignment::factory()->create([
            'exam_id' => $assignment->exam_id,
            'recruitment_application_id' => $assignment->recruitment_application_id,
        ]);
    }

    public function test_material_progress_is_unique_per_candidate_and_material()
    {
        $progress = MaterialProgress::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        MaterialProgress::factory()->create([
            'candidate_profile_id' => $progress->candidate_profile_id,
            'learning_material_id' => $progress->learning_material_id,
        ]);
    }

    public function test_material_with_progress_cannot_be_hard_deleted()
    {
        $progress = MaterialProgress::factory()->create();

        $this->expectException(QueryException::class);

        LearningMaterial::query()->whereKey($progress->learning_material_id)->delete();
    }

    public function test_accepted_answers_are_normalized_on_assignment()
    {
        $question = Question::factory()->shortAnswer()->create();

        $answer = $question->acceptedAnswers()->create(['answer' => '  Jakarta   Selatan ']);

        $this->assertSame('Jakarta   Selatan', $answer->answer);
        $this->assertSame('jakarta selatan', $answer->normalized);

        $this->expectException(UniqueConstraintViolationException::class);

        $question->acceptedAnswers()->create(['answer' => 'JAKARTA selatan']);
    }
}
