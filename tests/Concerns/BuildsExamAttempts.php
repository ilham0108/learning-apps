<?php

namespace Tests\Concerns;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Models\AttemptQuestion;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\QuestionBank\Models\Question;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyiapkan kandidat dengan penugasan ujian dan membantu memanggil endpoint runner.
 */
trait BuildsExamAttempts
{
    /**
     * @param  array<int, Question>  $questions
     * @param  array<string, mixed>  $examAttributes
     */
    protected function assignExam(array $questions, array $examAttributes = [], ?RecruitmentCampaign $campaign = null, ?CandidateProfile $profile = null): ExamAssignment
    {
        $profile ??= CandidateProfile::factory()->activated()->withCompleteProfile()->create();
        $application = RecruitmentApplication::factory()->for($profile)->create(
            $campaign === null ? [] : ['recruitment_campaign_id' => $campaign->id],
        );
        $exam = Exam::factory()->published()->withQuestions($questions)->create($examAttributes);

        return ExamAssignment::factory()->create([
            'exam_id' => $exam->id,
            'recruitment_application_id' => $application->id,
        ]);
    }

    protected function startAttempt(ExamAssignment $assignment): ExamAttempt
    {
        $this->actingAs($assignment->application->candidateProfile->user)
            ->post(route('exams.start', $assignment))
            ->assertSessionHasNoErrors()
            ->assertRedirectContains('/attempts/');

        return $assignment->attempts()->reorder()->latest('id')->firstOrFail();
    }

    /**
     * Membuka runner dan mengembalikan token sesi yang diterbitkan.
     */
    protected function openRunner(ExamAttempt $attempt): string
    {
        return $this->get(route('attempts.show', $attempt))->inertiaProps('sessionToken');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    protected function saveAnswer(ExamAttempt $attempt, string $session, AttemptQuestion $question, array $payload): TestResponse
    {
        return $this->putJson(
            route('attempts.answers.update', [$attempt, $question->token]),
            ['revision' => 1, ...$payload],
            ['X-Attempt-Session' => $session],
        );
    }

    /**
     * @return TestResponse<Response>
     */
    protected function submitAttempt(ExamAttempt $attempt, string $session): TestResponse
    {
        return $this->postJson(route('attempts.submit', $attempt), [], ['X-Attempt-Session' => $session]);
    }

    protected function snapshotOf(ExamAttempt $attempt, Question $source): AttemptQuestion
    {
        return $attempt->questions()->where('source_question_id', $source->id)->with('options')->firstOrFail();
    }

    /**
     * Token pilihan benar (atau salah) dari snapshot soal.
     *
     * @return array<int, string>
     */
    protected function optionTokens(AttemptQuestion $question, bool $correct): array
    {
        return $question->options->where('is_correct', $correct)->pluck('token')->values()->all();
    }
}
