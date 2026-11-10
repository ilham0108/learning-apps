<?php

namespace Tests\Feature\Candidates;

use App\Domain\Candidates\Enums\EvaluationStatus;
use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\CandidateEvaluation;
use App\Domain\Candidates\Models\InternalNote;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private RecruitmentApplication $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->hr()->create();
        $this->application = RecruitmentApplication::factory()
            ->for(RecruitmentCampaign::factory()->assignedTo($this->hr), 'campaign')
            ->create(['stage' => RecruitmentStage::UnderReview]);
    }

    public function test_hr_can_change_the_stage_and_it_is_recorded()
    {
        $this->actingAs($this->hr)
            ->patch(route('admin.applications.stage.update', $this->application), ['stage' => 'shortlisted'])
            ->assertSessionHasNoErrors();

        $this->assertSame(RecruitmentStage::Shortlisted, $this->application->refresh()->stage);

        $history = $this->application->stageHistories()->sole();
        $this->assertSame(RecruitmentStage::UnderReview, $history->from_stage);
        $this->assertSame($this->hr->id, $history->changed_by);

        $log = ActivityLog::query()->where('action', 'application.stage_changed')->sole();
        // Kolom JSON MySQL tidak mempertahankan urutan kunci.
        $this->assertEquals(['from' => 'under_review', 'to' => 'shortlisted'], $log->properties);
    }

    public function test_invalid_transitions_are_rejected()
    {
        $this->actingAs($this->hr);

        $this->patch(route('admin.applications.stage.update', $this->application), ['stage' => 'hired'])
            ->assertSessionHasErrors('stage');
        $this->patch(route('admin.applications.stage.update', $this->application), ['stage' => 'tidak-ada'])
            ->assertSessionHasErrors('stage');

        $invited = RecruitmentApplication::factory()->for($this->application->campaign, 'campaign')->create();
        $this->patch(route('admin.applications.stage.update', $invited), ['stage' => 'registered'])
            ->assertSessionHasErrors('stage');

        $this->assertSame(RecruitmentStage::UnderReview, $this->application->refresh()->stage);
    }

    public function test_rejection_requires_a_reason()
    {
        $this->actingAs($this->hr);

        $this->patch(route('admin.applications.stage.update', $this->application), ['stage' => 'rejected', 'reason' => '  '])
            ->assertSessionHasErrors('reason');

        $this->patch(route('admin.applications.stage.update', $this->application), ['stage' => 'rejected', 'reason' => 'Tidak hadir wawancara'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Tidak hadir wawancara', $this->application->stageHistories()->sole()->reason);
    }

    public function test_only_super_admin_can_correct_a_rejection_or_change_closed_campaigns()
    {
        $this->application->update(['stage' => RecruitmentStage::Rejected]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($this->hr)
            ->patch(route('admin.applications.stage.update', $this->application), ['stage' => 'under_review', 'reason' => 'Salah input'])
            ->assertSessionHasErrors('stage');

        $this->actingAs($admin)
            ->patch(route('admin.applications.stage.update', $this->application), ['stage' => 'under_review', 'reason' => 'Salah input'])
            ->assertSessionHasNoErrors();

        $this->application->campaign->update(['status' => 'closed']);

        $this->actingAs($this->hr)
            ->patch(route('admin.applications.stage.update', $this->application), ['stage' => 'shortlisted'])
            ->assertSessionHasErrors('stage');

        $this->actingAs($admin)
            ->patch(route('admin.applications.stage.update', $this->application), ['stage' => 'shortlisted'])
            ->assertSessionHasNoErrors();
    }

    public function test_the_ui_only_offers_allowed_stages()
    {
        $this->actingAs($this->hr)
            ->get(route('admin.candidates.show', $this->application->candidateProfile))
            ->assertInertia(fn (Assert $page) => $page
                ->where('candidate.applications.0.allowed_stages', ['shortlisted', 'rejected', 'withdrawn']));
    }

    public function test_out_of_scope_applications_return_not_found()
    {
        $other = RecruitmentApplication::factory()->create(['stage' => RecruitmentStage::UnderReview]);
        $this->actingAs($this->hr);

        $this->patch(route('admin.applications.stage.update', $other), ['stage' => 'shortlisted'])->assertNotFound();
        $this->post(route('admin.applications.notes.store', $other), ['body' => 'x'])->assertNotFound();
        $this->put(route('admin.applications.evaluation.update', $other), $this->evaluationPayload())->assertNotFound();
    }

    public function test_notes_can_only_be_changed_by_their_author_or_super_admin()
    {
        $this->actingAs($this->hr)
            ->post(route('admin.applications.notes.store', $this->application), ['body' => 'Komunikatif'])
            ->assertSessionHasNoErrors();
        $note = InternalNote::query()->sole();

        $colleague = User::factory()->hr()->create();
        $this->application->campaign->hrUsers()->attach($colleague->id);

        $this->actingAs($colleague)->patch(route('admin.notes.update', $note), ['body' => 'Diubah'])->assertForbidden();
        $this->actingAs($colleague)->delete(route('admin.notes.destroy', $note))->assertForbidden();

        $this->actingAs(User::factory()->hr()->create())->patch(route('admin.notes.update', $note), ['body' => 'x'])->assertNotFound();

        $this->actingAs($this->hr)->patch(route('admin.notes.update', $note), ['body' => 'Sangat komunikatif'])->assertSessionHasNoErrors();
        $this->assertSame('Sangat komunikatif', $note->refresh()->body);

        $this->actingAs(User::factory()->superAdmin()->create())->delete(route('admin.notes.destroy', $note))->assertSessionHasNoErrors();
        $this->assertModelMissing($note);
        $this->assertTrue(ActivityLog::query()->where('action', 'internal_note.deleted')->exists());
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluationPayload(array $overrides = []): array
    {
        return [
            'scores' => [
                'exam' => ['score' => 4, 'note' => 'Nilai baik'],
                'learning' => ['score' => 5, 'note' => null],
                'interview' => ['score' => 3, 'note' => 'Perlu latihan presentasi'],
                'attitude' => ['score' => 5, 'note' => null],
                'other' => ['score' => null, 'note' => null],
            ],
            'recommendation' => 'yes',
            'summary' => 'Layak lanjut ke tahap berikutnya.',
            'finalize' => false,
            ...$overrides,
        ];
    }

    public function test_evaluations_can_be_drafted_then_finalized_and_are_locked_afterwards()
    {
        $this->actingAs($this->hr);

        $this->put(route('admin.applications.evaluation.update', $this->application), $this->evaluationPayload())
            ->assertSessionHasNoErrors();
        $evaluation = CandidateEvaluation::query()->sole();
        $this->assertSame(EvaluationStatus::Draft, $evaluation->status);
        $this->assertSame(5, $evaluation->scores()->count());

        $this->put(route('admin.applications.evaluation.update', $this->application), $this->evaluationPayload(['finalize' => true]))
            ->assertSessionHasNoErrors();
        $this->assertSame(EvaluationStatus::Final, $evaluation->refresh()->status);
        $this->assertTrue(ActivityLog::query()->where('action', 'evaluation.finalized')->exists());

        $this->put(route('admin.applications.evaluation.update', $this->application), $this->evaluationPayload(['summary' => 'Ubah']))
            ->assertSessionHasErrors('evaluation');
        $this->assertSame(1, CandidateEvaluation::query()->count());
    }

    public function test_evaluation_validation()
    {
        $this->actingAs($this->hr);

        $this->put(route('admin.applications.evaluation.update', $this->application), $this->evaluationPayload([
            'finalize' => true,
            'recommendation' => null,
        ]))->assertSessionHasErrors('recommendation');

        $payload = $this->evaluationPayload();
        $payload['scores']['exam']['score'] = 6;
        $this->put(route('admin.applications.evaluation.update', $this->application), $payload)
            ->assertSessionHasErrors('scores.exam.score');

        $payload = $this->evaluationPayload();
        $payload['scores']['salary'] = ['score' => 5, 'note' => null];
        $this->put(route('admin.applications.evaluation.update', $this->application), $payload)
            ->assertSessionHasErrors('scores');
    }

    public function test_each_evaluator_has_their_own_evaluation()
    {
        $colleague = User::factory()->hr()->create();
        $this->application->campaign->hrUsers()->attach($colleague->id);

        $this->actingAs($this->hr)->put(route('admin.applications.evaluation.update', $this->application), $this->evaluationPayload());
        $this->actingAs($colleague)->put(route('admin.applications.evaluation.update', $this->application), $this->evaluationPayload());

        $this->assertSame(2, CandidateEvaluation::query()->count());

        $this->actingAs($colleague)
            ->get(route('admin.candidates.show', $this->application->candidateProfile))
            ->assertInertia(fn (Assert $page) => $page
                ->has('candidate.applications.0.evaluations', 2)
                ->where('candidate.applications.0.evaluations.0.is_mine', false)
                ->where('candidate.applications.0.evaluations.1.is_mine', true));
    }
}
