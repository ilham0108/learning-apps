<?php

namespace Tests\Feature\Exams;

use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Enums\AnswerGradingStatus;
use App\Domain\Exams\Enums\AttemptResult;
use App\Domain\Exams\Enums\GradingStatus;
use App\Domain\Exams\Enums\MultiSelectScoring;
use App\Domain\Exams\Enums\ResultRelease;
use App\Domain\Exams\Enums\ScorePolicy;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\QuestionBank\Models\QuestionCategory;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsNoInternalIds;
use Tests\Concerns\BuildsExamAttempts;
use Tests\TestCase;

class GradingTest extends TestCase
{
    use AssertsNoInternalIds, BuildsExamAttempts, RefreshDatabase;

    /**
     * Menyimpan jawaban lalu mengumpulkan attempt.
     *
     * @param  array<int, array{0: Question, 1: array<string, mixed>}>  $answers
     */
    private function answerAndSubmit(ExamAttempt $attempt, array $answers): ExamAttempt
    {
        $session = $this->openRunner($attempt);

        foreach ($answers as [$question, $payload]) {
            $this->saveAnswer($attempt, $session, $this->snapshotOf($attempt, $question), $payload)->assertOk();
        }

        $this->submitAttempt($attempt, $session)->assertOk();

        return $attempt->fresh();
    }

    public function test_objective_questions_are_graded_with_weights_and_categories()
    {
        $knowledge = QuestionCategory::factory()->create(['name' => 'Pengetahuan']);
        $logic = QuestionCategory::factory()->create(['name' => 'Logika']);
        $mc = Question::factory()->multipleChoice()->for($knowledge, 'category')->create(['default_points' => 30]);
        $tf = Question::factory()->trueFalse()->for($knowledge, 'category')->create(['default_points' => 10]);
        $short = Question::factory()->shortAnswer()->for($logic, 'category')->create(['default_points' => 20]);
        $short->acceptedAnswers()->delete();
        $short->acceptedAnswers()->create(['answer' => 'Jakarta Pusat']);
        $wrong = Question::factory()->multipleChoice()->for($logic, 'category')->create(['default_points' => 40]);

        $attempt = $this->startAttempt($this->assignExam([$mc, $tf, $short, $wrong], ['passing_grade' => 60]));
        $snapshotMc = $this->snapshotOf($attempt, $mc);
        $snapshotTf = $this->snapshotOf($attempt, $tf);
        $snapshotWrong = $this->snapshotOf($attempt, $wrong);

        $attempt = $this->answerAndSubmit($attempt, [
            [$mc, ['selected' => $this->optionTokens($snapshotMc, true)]],
            [$tf, ['selected' => $this->optionTokens($snapshotTf, false)]],
            // Normalisasi: huruf besar/kecil dan spasi berlebih diabaikan.
            [$short, ['text' => '  jakarta   PUSAT ']],
            [$wrong, ['selected' => [$this->optionTokens($snapshotWrong, false)[0]]]],
        ]);

        // 30 + 0 + 20 + 0 dari 100.
        $this->assertSame('50.00', $attempt->earned_points);
        $this->assertSame('50.00', $attempt->score_percent);
        $this->assertSame(GradingStatus::Graded, $attempt->grading_status);
        $this->assertSame(AttemptResult::Failed, $attempt->result);
        $this->assertNotNull($attempt->finalized_at);

        $categories = $attempt->categoryScores()->get()->keyBy('category_name');
        $this->assertSame(['30.00', '40.00'], [$categories['Pengetahuan']->earned_points, $categories['Pengetahuan']->max_points]);
        $this->assertSame(['20.00', '60.00'], [$categories['Logika']->earned_points, $categories['Logika']->max_points]);
        $this->assertTrue($attempt->assignment->fresh()->effective_attempt_id === $attempt->id);
    }

    public function test_multiple_select_supports_all_or_nothing_and_partial_scoring()
    {
        // Factory: 4 pilihan, pilihan 1 dan 3 benar.
        foreach ([[MultiSelectScoring::AllOrNothing, '0.00'], [MultiSelectScoring::Partial, '5.00']] as [$scoring, $expected]) {
            $question = Question::factory()->multipleSelect()->create(['default_points' => 10]);
            $attempt = $this->startAttempt($this->assignExam([$question], ['multi_select_scoring' => $scoring]));
            $snapshot = $this->snapshotOf($attempt, $question);
            $correct = $this->optionTokens($snapshot, true);

            // Dua benar + satu salah: parsial = (2 − 1) / 2 × 10 = 5.
            $attempt = $this->answerAndSubmit($attempt, [
                [$question, ['selected' => [...$correct, $this->optionTokens($snapshot, false)[0]]]],
            ]);

            $this->assertSame($expected, $attempt->earned_points, $scoring->value);
        }

        $question = Question::factory()->multipleSelect()->create(['default_points' => 10]);
        $attempt = $this->startAttempt($this->assignExam([$question], ['multi_select_scoring' => MultiSelectScoring::Partial]));
        $snapshot = $this->snapshotOf($attempt, $question);
        // Parsial tidak pernah negatif: satu benar + dua salah = max(0, −1/2).
        $attempt = $this->answerAndSubmit($attempt, [
            [$question, ['selected' => [$this->optionTokens($snapshot, true)[0], ...$this->optionTokens($snapshot, false)]]],
        ]);
        $this->assertSame('0.00', $attempt->earned_points);
    }

    public function test_essays_and_unmatched_short_answers_wait_for_manual_grading()
    {
        $hr = User::factory()->hr()->create();
        $campaign = RecruitmentCampaign::factory()->assignedTo($hr)->create();
        $essay = Question::factory()->essay()->create(['default_points' => 50]);
        $short = Question::factory()->shortAnswer()->create(['default_points' => 50]);
        $assignment = $this->assignExam([$essay, $short], ['passing_grade' => 70], $campaign);

        $attempt = $this->answerAndSubmit($this->startAttempt($assignment), [
            [$essay, ['text' => 'Penjelasan saya tentang SOP.']],
            [$short, ['text' => 'jawaban lain']],
        ]);

        $this->assertSame(GradingStatus::PendingManual, $attempt->grading_status);
        $this->assertNull($attempt->result);
        $this->assertSame(2, $attempt->answers()->where('grading_status', AnswerGradingStatus::PendingManual)->count());

        // Kandidat tidak melihat nilai sementara.
        $this->get(route('attempts.result', $attempt))->assertInertia(fn (Assert $page) => $page
            ->component('candidate/attempts/result')
            ->where('attempt.score_visible', false)
            ->where('attempt.score_percent', null)
            ->where('review', null));

        $this->actingAs($hr);
        $this->get(route('admin.grading.index'))->assertInertia(fn (Assert $page) => $page
            ->where('attempts.data.0.id', $attempt->public_id)
            ->where('attempts.data.0.pending_count', 2));

        $this->put(route('admin.attempts.grade', [$attempt, $this->snapshotOf($attempt, $essay)->token]), ['points' => 60])
            ->assertSessionHasErrors('points');
        $this->put(route('admin.attempts.grade', [$attempt, $this->snapshotOf($attempt, $essay)->token]), ['points' => 40, 'note' => 'Cukup lengkap'])
            ->assertSessionHasNoErrors();
        $this->assertSame(GradingStatus::PendingManual, $attempt->fresh()->grading_status);
        $this->assertSame('40.00', $attempt->fresh()->score_percent, 'Nilai sementara.');

        $this->put(route('admin.attempts.grade', [$attempt, $this->snapshotOf($attempt, $short)->token]), ['points' => 50])
            ->assertSessionHasNoErrors();

        $attempt->refresh();
        $this->assertSame(GradingStatus::Graded, $attempt->grading_status);
        $this->assertSame('90.00', $attempt->score_percent);
        $this->assertSame(AttemptResult::Passed, $attempt->result);
        $answer = $attempt->answers()->whereHas('question', fn ($query) => $query->where('source_question_id', $essay->id))->sole();
        $this->assertSame($hr->id, $answer->graded_by);
        $this->assertSame('Cukup lengkap', $answer->grader_note);
        $this->assertSame(0, ActivityLog::query()->where('action', 'score.changed')->count(), 'Belum final: tidak perlu audit.');
    }

    public function test_changing_a_final_score_requires_a_reason_and_is_audited()
    {
        $hr = User::factory()->hr()->create();
        $campaign = RecruitmentCampaign::factory()->assignedTo($hr)->create();
        $essay = Question::factory()->essay()->create(['default_points' => 10]);
        $attempt = $this->answerAndSubmit($this->startAttempt($this->assignExam([$essay], [], $campaign)), [[$essay, ['text' => 'Jawaban']]]);
        $token = $this->snapshotOf($attempt, $essay)->token;

        $this->actingAs($hr)->put(route('admin.attempts.grade', [$attempt, $token]), ['points' => 8])->assertSessionHasNoErrors();
        $this->assertTrue($attempt->fresh()->isGraded());

        $this->put(route('admin.attempts.grade', [$attempt, $token]), ['points' => 5])->assertSessionHasErrors('reason');
        $this->assertSame('80.00', $attempt->fresh()->score_percent);

        $this->put(route('admin.attempts.grade', [$attempt, $token]), ['points' => 5, 'reason' => 'Salah membaca rubrik'])
            ->assertSessionHasNoErrors();

        $this->assertSame('50.00', $attempt->fresh()->score_percent);
        $log = ActivityLog::query()->where('action', 'score.changed')->sole();
        $this->assertSame('Salah membaca rubrik', $log->reason);
        $this->assertSame('80.00', $log->properties['score_percent']['from']);
    }

    public function test_hr_outside_the_campaign_cannot_view_or_grade_attempts()
    {
        $essay = Question::factory()->essay()->create();
        $attempt = $this->answerAndSubmit($this->startAttempt($this->assignExam([$essay])), [[$essay, ['text' => 'Jawaban']]]);
        $outsider = User::factory()->hr()->create();

        $this->actingAs($outsider);
        $this->get(route('admin.attempts.show', $attempt))->assertNotFound();
        $this->put(route('admin.attempts.grade', [$attempt, $this->snapshotOf($attempt, $essay)->token]), ['points' => 1])->assertNotFound();
        $this->get(route('admin.grading.index'))->assertInertia(fn (Assert $page) => $page->where('attempts.data', []));
        $this->get(route('admin.exams.attempts.index', $attempt->assignment->exam))->assertInertia(fn (Assert $page) => $page->where('attempts.data', []));

        // Super Admin melihat semua, dengan kunci jawaban untuk penilai.
        $response = $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.attempts.show', $attempt))->assertOk();
        $this->assertResponseHasNoInternalIds($response);
    }

    public function test_score_policy_selects_the_effective_attempt()
    {
        foreach ([[ScorePolicy::Highest, 1], [ScorePolicy::Latest, 2]] as [$policy, $expectedNumber]) {
            $question = Question::factory()->multipleChoice()->create();
            $assignment = $this->assignExam([$question], ['max_attempts' => 2, 'score_policy' => $policy]);

            $first = $this->startAttempt($assignment);
            $this->answerAndSubmit($first, [[$question, ['selected' => $this->optionTokens($this->snapshotOf($first, $question), true)]]]);
            $second = $this->startAttempt($assignment);
            $this->answerAndSubmit($second, [[$question, ['selected' => [$this->optionTokens($this->snapshotOf($second, $question), false)[0]]]]]);

            $effective = $assignment->fresh()->effectiveAttempt;
            $this->assertSame($expectedNumber, $effective->attempt_number, $policy->value);
        }
    }

    public function test_results_follow_the_release_policy_and_review_setting()
    {
        $hr = User::factory()->hr()->create();
        $question = Question::factory()->multipleChoice()->create(['explanation_html' => '<p>Pembahasan rahasia</p>']);
        $assignment = $this->assignExam([$question], ['result_release' => ResultRelease::Manual, 'show_review' => true]);
        $attempt = $this->startAttempt($assignment);
        $attempt = $this->answerAndSubmit($attempt, [[$question, ['selected' => $this->optionTokens($this->snapshotOf($attempt, $question), true)]]]);
        $candidate = $assignment->application->candidateProfile->user;

        $hidden = $this->get(route('attempts.result', $attempt))->assertInertia(fn (Assert $page) => $page
            ->where('attempt.score_visible', false)
            ->where('review', null));
        $this->assertStringNotContainsString('Pembahasan rahasia', $hidden->getContent());
        $this->assertStringNotContainsString('is_correct', json_encode($hidden->inertiaProps()));

        $this->actingAs($hr)->post(route('admin.exams.release-results', $assignment->exam))->assertRedirect();
        $this->assertTrue(ActivityLog::query()->where('action', 'exam.results_released')->exists());

        $shown = $this->actingAs($candidate)->get(route('attempts.result', $attempt))->assertInertia(fn (Assert $page) => $page
            ->where('attempt.score_visible', true)
            ->where('attempt.score_percent', '100.00')
            ->where('review.0.explanation_html', '<p>Pembahasan rahasia</p>')
            ->has('categories', 1));
        $this->assertResponseHasNoInternalIds($shown);

        // show_review = false: nilai terlihat, kunci dan pembahasan tidak.
        $assignment->exam->update(['show_review' => false]);
        $this->get(route('attempts.result', $attempt))->assertInertia(fn (Assert $page) => $page
            ->where('attempt.score_visible', true)
            ->where('review', null));
    }

    public function test_after_close_release_waits_for_the_effective_close_time()
    {
        $question = Question::factory()->multipleChoice()->create();
        $assignment = $this->assignExam([$question], ['result_release' => ResultRelease::AfterClose, 'closes_at' => now()->addHours(2)]);
        $attempt = $this->startAttempt($assignment);
        $this->answerAndSubmit($attempt, [[$question, ['selected' => []]]]);

        $this->get(route('attempts.result', $attempt))->assertInertia(fn (Assert $page) => $page->where('attempt.score_visible', false));

        $this->travel(3)->hours();
        $this->get(route('attempts.result', $attempt))->assertInertia(fn (Assert $page) => $page
            ->where('attempt.score_visible', true)
            ->where('attempt.result', 'failed'));
    }
}
