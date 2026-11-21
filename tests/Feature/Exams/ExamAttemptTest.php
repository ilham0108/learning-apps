<?php

namespace Tests\Feature\Exams;

use App\Domain\Candidates\Actions\SetCandidateActive;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Exams\Enums\AttemptEventType;
use App\Domain\Exams\Enums\AttemptStatus;
use App\Domain\Exams\Enums\NavigationMode;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\MaterialProgress;
use App\Domain\QuestionBank\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsNoInternalIds;
use Tests\Concerns\BuildsExamAttempts;
use Tests\TestCase;

class ExamAttemptTest extends TestCase
{
    use AssertsNoInternalIds, BuildsExamAttempts, RefreshDatabase;

    public function test_starting_creates_a_snapshot_locks_the_exam_and_caps_the_deadline()
    {
        $this->travelTo(now()->startOfMinute());
        $questions = [Question::factory()->multipleChoice()->create(), Question::factory()->caseStudy()->create()];
        $assignment = $this->assignExam($questions, ['duration_minutes' => 60, 'closes_at' => now()->addMinutes(30)]);

        $attempt = $this->startAttempt($assignment);

        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertSame(1, $attempt->attempt_number);
        // Waktu tutup lebih awal dari durasi: deadline = waktu tutup.
        $this->assertTrue($attempt->deadline_at->equalTo(now()->addMinutes(30)));
        $this->assertNotNull($assignment->exam->fresh()->locked_at);
        // Pilihan ganda + induk studi kasus + dua sub-soal.
        $this->assertSame(4, $attempt->questions()->count());
        $this->assertSame(4, $attempt->questions()->where('type', 'multiple_choice')->first()->options()->count());
        // Induk studi kasus 0 poin; poin maksimal = 10 + 10 + 10.
        $this->assertSame('30.00', $attempt->max_points);
        $this->assertTrue($attempt->events()->where('type', AttemptEventType::Started)->exists());
    }

    public function test_starting_again_resumes_the_running_attempt()
    {
        $assignment = $this->assignExam([Question::factory()->create()], ['max_attempts' => 3]);

        $first = $this->startAttempt($assignment);
        $second = $this->startAttempt($assignment);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $assignment->attempts()->count());
    }

    public function test_schedule_and_attempt_limits_are_enforced_by_server_time()
    {
        $assignment = $this->assignExam([Question::factory()->create()], ['opens_at' => now()->addHour()]);
        $user = $assignment->application->candidateProfile->user;

        $this->actingAs($user)->post(route('exams.start', $assignment))->assertRedirect(route('exams.show', $assignment));
        $this->assertSame(0, $assignment->attempts()->count());

        // Jadwal khusus individu menimpa jadwal ujian.
        $assignment->update(['opens_at_override' => now()->subMinute()]);
        $attempt = $this->startAttempt($assignment);
        $this->submitAttempt($attempt, $this->openRunner($attempt))->assertOk();

        // Batas satu percobaan sudah terpakai.
        $this->post(route('exams.start', $assignment))->assertRedirect(route('exams.show', $assignment));
        $this->assertSame(1, $assignment->attempts()->count());

        $this->travel(15)->days();
        $assignment->update(['max_attempts_override' => 2]);
        $this->post(route('exams.start', $assignment));
        $this->assertSame(1, $assignment->attempts()->count(), 'Ujian yang sudah ditutup tidak bisa dimulai.');
    }

    public function test_required_materials_of_prerequisite_programs_must_be_completed()
    {
        $assignment = $this->assignExam([Question::factory()->create()]);
        $program = LearningProgram::factory()->published()->create();
        $module = LearningModule::factory()->for($program, 'program')->create();
        $material = LearningMaterial::factory()->for($module, 'module')->create();
        $assignment->exam->prerequisitePrograms()->attach($program->id);
        $profile = $assignment->application->candidateProfile;

        $this->actingAs($profile->user)->get(route('exams.show', $assignment))
            ->assertInertia(fn (Assert $page) => $page
                ->where('assignment.availability', 'prerequisites_incomplete')
                ->where('assignment.incomplete_programs.0.id', $program->public_id));
        $this->post(route('exams.start', $assignment));
        $this->assertSame(0, $assignment->attempts()->count());

        MaterialProgress::query()->create([
            'candidate_profile_id' => $profile->id,
            'learning_material_id' => $material->id,
            'first_opened_at' => now(),
            'completed_at' => now(),
        ]);

        $this->startAttempt($assignment);
        $this->assertSame(1, $assignment->attempts()->count());
    }

    public function test_runner_never_receives_answer_keys_or_internal_ids()
    {
        $questions = [
            Question::factory()->multipleChoice()->create(),
            Question::factory()->shortAnswer()->create(),
            Question::factory()->caseStudy()->create(),
        ];
        $attempt = $this->startAttempt($this->assignExam($questions));

        $response = $this->get(route('attempts.show', $attempt))->assertOk();
        $this->assertResponseHasNoInternalIds($response);
        $json = json_encode($response->inertiaProps());
        $this->assertStringNotContainsString('is_correct', $json);
        $this->assertStringNotContainsString('explanation', $json);
        $this->assertStringNotContainsString('accepted', $json);
        $this->assertStringNotContainsString($questions[0]->public_id, $json);

        $state = $this->getJson(route('attempts.state', $attempt), ['X-Attempt-Session' => $response->inertiaProps('sessionToken')])
            ->assertOk()
            ->assertJsonCount(3, 'items')
            ->assertJsonCount(2, 'items.2.children');
        $this->assertResponseHasNoInternalIds($state);
        $this->assertStringNotContainsString('is_correct', $state->getContent());
    }

    public function test_answers_are_saved_one_row_per_question_with_revisions()
    {
        $question = Question::factory()->multipleSelect()->create();
        $attempt = $this->startAttempt($this->assignExam([$question]));
        $session = $this->openRunner($attempt);
        $snapshot = $this->snapshotOf($attempt, $question);
        $correct = $this->optionTokens($snapshot, true);

        $this->saveAnswer($attempt, $session, $snapshot, ['revision' => 2, 'selected' => $correct, 'flagged' => true])
            ->assertOk()
            ->assertJson(['revision' => 2]);

        // Revisi lama dari antrean kirim ulang tidak menimpa jawaban yang lebih baru.
        $this->saveAnswer($attempt, $session, $snapshot, ['revision' => 1, 'selected' => []])
            ->assertOk()
            ->assertJson(['revision' => 2]);

        $answer = $attempt->answers()->sole();
        $this->assertTrue($answer->is_flagged);
        $this->assertEqualsCanonicalizing($correct, $answer->selectedOptions()->pluck('token')->all());

        // Token pilihan dari soal lain atau tidak dikenal ditolak.
        $this->saveAnswer($attempt, $session, $snapshot, ['revision' => 3, 'selected' => [strtolower((string) str()->ulid())]])
            ->assertUnprocessable();
    }

    public function test_single_choice_questions_accept_only_one_option()
    {
        $question = Question::factory()->multipleChoice()->create();
        $attempt = $this->startAttempt($this->assignExam([$question]));
        $snapshot = $this->snapshotOf($attempt, $question);

        $this->saveAnswer($attempt, $this->openRunner($attempt), $snapshot, ['selected' => $snapshot->options->take(2)->pluck('token')->all()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('selected');
    }

    public function test_saving_after_the_deadline_is_rejected_and_triggers_auto_submit()
    {
        $question = Question::factory()->essay()->create();
        $attempt = $this->startAttempt($this->assignExam([$question], ['duration_minutes' => 10]));
        $session = $this->openRunner($attempt);
        $snapshot = $this->snapshotOf($attempt, $question);

        $this->saveAnswer($attempt, $session, $snapshot, ['text' => 'Jawaban awal'])->assertOk();

        $this->travel(11)->minutes();

        $this->saveAnswer($attempt, $session, $snapshot, ['revision' => 2, 'text' => 'Terlambat'])
            ->assertStatus(409)
            ->assertJson(['code' => 'finished', 'status' => 'auto_submitted'])
            ->assertJsonPath('result_url', route('attempts.result', $attempt, false));

        $this->assertSame('Jawaban awal', $attempt->answers()->sole()->text_answer);
        $this->assertTrue($attempt->fresh()->submitted_at->equalTo($attempt->deadline_at));
    }

    public function test_only_the_latest_runner_session_can_save()
    {
        $question = Question::factory()->essay()->create();
        $attempt = $this->startAttempt($this->assignExam([$question]));
        $snapshot = $this->snapshotOf($attempt, $question);

        $oldSession = $this->openRunner($attempt);
        $newSession = $this->openRunner($attempt);

        $this->saveAnswer($attempt, $oldSession, $snapshot, ['text' => 'Tab lama'])
            ->assertStatus(409)
            ->assertJson(['code' => 'session_replaced']);
        $this->saveAnswer($attempt, $newSession, $snapshot, ['text' => 'Tab baru'])->assertOk();

        $this->assertTrue($attempt->events()->where('type', AttemptEventType::SessionReplaced)->exists());
        $this->assertTrue($attempt->events()->where('type', AttemptEventType::Resumed)->exists());
    }

    public function test_forward_only_navigation_locks_previous_questions()
    {
        $questions = [Question::factory()->essay()->create(), Question::factory()->essay()->create()];
        $attempt = $this->startAttempt($this->assignExam($questions, ['navigation' => NavigationMode::ForwardOnly]));
        $session = $this->openRunner($attempt);
        $headers = ['X-Attempt-Session' => $session];

        $this->postJson(route('attempts.position', $attempt), ['position' => 2], $headers)
            ->assertOk()
            ->assertJson(['current_position' => 2, 'furthest_position' => 2]);
        $this->postJson(route('attempts.position', $attempt), ['position' => 1], $headers)
            ->assertStatus(409)
            ->assertJson(['code' => 'question_locked']);

        $first = $attempt->questions()->where('position', 1)->first();
        $this->saveAnswer($attempt, $session, $first, ['text' => 'Mundur'])->assertStatus(409);

        // Posisi dipulihkan dari server setelah refresh.
        $this->get(route('attempts.show', $attempt))->assertInertia(fn (Assert $page) => $page
            ->where('attempt.current_position', 2));
    }

    public function test_submit_is_idempotent()
    {
        $question = Question::factory()->multipleChoice()->create();
        $attempt = $this->startAttempt($this->assignExam([$question]));
        $session = $this->openRunner($attempt);
        $snapshot = $this->snapshotOf($attempt, $question);
        $this->saveAnswer($attempt, $session, $snapshot, ['selected' => $this->optionTokens($snapshot, true)]);

        $this->submitAttempt($attempt, $session)->assertOk()->assertJson(['status' => 'submitted']);
        $firstFinalized = $attempt->fresh()->finalized_at;

        $this->travel(1)->minutes();
        $this->submitAttempt($attempt, $session)->assertOk()->assertJson(['status' => 'submitted']);

        $attempt->refresh();
        $this->assertSame('100.00', $attempt->score_percent);
        $this->assertTrue($attempt->finalized_at->equalTo($firstFinalized));
        $this->assertSame(1, $attempt->events()->where('type', AttemptEventType::Submitted)->count());
        $this->assertSame(1, $attempt->answers()->count());
    }

    public function test_scheduled_command_closes_expired_attempts()
    {
        $answered = $this->startAttempt($this->assignExam([$question = Question::factory()->essay()->create()], ['duration_minutes' => 5]));
        $this->saveAnswer($answered, $this->openRunner($answered), $this->snapshotOf($answered, $question), ['text' => 'Ada isinya']);
        $empty = $this->startAttempt($this->assignExam([Question::factory()->essay()->create()], ['duration_minutes' => 5]));
        $running = $this->startAttempt($this->assignExam([Question::factory()->essay()->create()], ['duration_minutes' => 60]));

        $this->travel(6)->minutes();
        $this->artisan('exams:close-expired')->assertSuccessful();

        $this->assertSame(AttemptStatus::AutoSubmitted, $answered->fresh()->status);
        $this->assertSame(AttemptStatus::Expired, $empty->fresh()->status);
        $this->assertSame('0.00', $empty->fresh()->score_percent);
        $this->assertSame(AttemptStatus::InProgress, $running->fresh()->status);
    }

    public function test_deactivating_a_candidate_auto_submits_running_attempts()
    {
        $attempt = $this->startAttempt($this->assignExam([Question::factory()->create()]));
        $profile = $attempt->assignment->application->candidateProfile;

        app(SetCandidateActive::class)($profile, false, 'Mengundurkan diri', User::factory()->superAdmin()->create());

        $this->assertSame(AttemptStatus::Expired, $attempt->fresh()->status);
    }

    public function test_editing_the_question_bank_does_not_change_existing_attempts()
    {
        $question = Question::factory()->multipleChoice()->create(['body_html' => '<p>Soal asli</p>']);
        $attempt = $this->startAttempt($this->assignExam([$question]));

        $question->update(['body_html' => '<p>Soal diubah</p>']);
        $question->options()->update(['body_html' => 'Diubah']);

        $snapshot = $this->snapshotOf($attempt, $question);
        $this->assertSame('<p>Soal asli</p>', $snapshot->body_html);
        $this->assertFalse($snapshot->options->contains('body_html', 'Diubah'));
    }

    public function test_candidates_cannot_access_other_candidates_attempts_or_assignments()
    {
        $attempt = $this->startAttempt($this->assignExam([Question::factory()->create()]));
        $other = CandidateProfile::factory()->activated()->create();

        $this->actingAs($other->user);
        $this->get(route('exams.show', $attempt->assignment))->assertNotFound();
        $this->get(route('attempts.show', $attempt))->assertNotFound();
        $this->get(route('attempts.result', $attempt))->assertNotFound();
        $this->postJson(route('attempts.submit', $attempt))->assertNotFound();
        $this->assertSame(AttemptStatus::InProgress, $attempt->fresh()->status);
    }

    public function test_staff_cannot_take_exams()
    {
        $attempt = $this->startAttempt($this->assignExam([Question::factory()->create()]));

        $this->actingAs(User::factory()->superAdmin()->create());
        $this->post(route('exams.start', $attempt->assignment))->assertStatus(404);
        $this->postJson(route('attempts.submit', $attempt))->assertStatus(404);
    }

    public function test_exam_list_shows_availability_and_progress()
    {
        $assignment = $this->assignExam([Question::factory()->create()], ['max_attempts' => 2]);
        $this->actingAs($assignment->application->candidateProfile->user);

        $this->get(route('exams.index'))->assertInertia(fn (Assert $page) => $page
            ->component('candidate/exams/index')
            ->where('exams.0.availability', 'available')
            ->where('exams.0.progress', 'not_started')
            ->where('exams.0.max_attempts', 2));

        $this->startAttempt($assignment);

        $response = $this->get(route('exams.index'))->assertInertia(fn (Assert $page) => $page
            ->where('exams.0.availability', 'in_progress')
            ->where('exams.0.progress', 'in_progress'));
        $this->assertResponseHasNoInternalIds($response);
    }

    public function test_tab_events_are_logged()
    {
        $attempt = $this->startAttempt($this->assignExam([Question::factory()->create()]));
        $headers = ['X-Attempt-Session' => $this->openRunner($attempt)];

        $this->postJson(route('attempts.events.store', $attempt), ['type' => 'tab_hidden'], $headers)->assertOk();
        $this->postJson(route('attempts.events.store', $attempt), ['type' => 'submitted'], $headers)->assertUnprocessable();

        $this->assertSame(1, $attempt->events()->where('type', AttemptEventType::TabHidden)->count());
    }

    public function test_clock_reports_server_time_and_deadline()
    {
        $attempt = $this->startAttempt($this->assignExam([Question::factory()->create()]));

        $this->getJson(route('attempts.clock', $attempt))
            ->assertOk()
            ->assertJson(['status' => 'in_progress', 'deadline_at' => $attempt->deadline_at->toIso8601String()])
            ->assertJsonStructure(['server_now']);
    }

    public function test_a_finished_attempt_redirects_from_the_runner_to_the_result()
    {
        $attempt = $this->startAttempt($this->assignExam([Question::factory()->create()]));
        $this->submitAttempt($attempt, $this->openRunner($attempt));

        $this->get(route('attempts.show', $attempt))->assertRedirect(route('attempts.result', $attempt));
        $this->assertInstanceOf(ExamAttempt::class, $attempt->fresh());
    }
}
