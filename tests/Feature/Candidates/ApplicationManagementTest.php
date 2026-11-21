<?php

namespace Tests\Feature\Candidates;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Models\JobRole;
use App\Domain\Candidates\Enums\EvaluationStatus;
use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\CampaignStage;
use App\Domain\Candidates\Models\CandidateEvaluation;
use App\Domain\Candidates\Models\InternalNote;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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
        // Alur default: 1. Ditinjau, 2. Lolos seleksi awal. Kandidat sedang di tahap 1.
        $this->application = RecruitmentApplication::factory()
            ->for(RecruitmentCampaign::factory()->assignedTo($this->hr), 'campaign')
            ->inProcess(1)
            ->create();
    }

    private function stage(int $position): CampaignStage
    {
        return $this->application->campaign->stages()->where('position', $position)->sole();
    }

    private function move(RecruitmentApplication $application, string $target, ?string $reason = null): TestResponse
    {
        return $this->patch(route('admin.applications.stage.update', $application), array_filter(['target' => $target, 'reason' => $reason]));
    }

    public function test_hr_moves_candidates_to_the_next_stage_and_it_is_recorded()
    {
        $this->actingAs($this->hr);
        $this->move($this->application, 'stage:'.$this->stage(2)->public_id)->assertSessionHasNoErrors();

        $this->application->refresh();
        $this->assertSame(RecruitmentStage::InProcess, $this->application->stage);
        $this->assertSame('Lolos seleksi awal', $this->application->stageLabel());

        $history = $this->application->stageHistories()->sole();
        $this->assertSame('Ditinjau', $history->from_label);
        $this->assertSame('Lolos seleksi awal', $history->to_label);
        $this->assertSame($this->hr->id, $history->changed_by);

        $log = ActivityLog::query()->where('action', 'application.stage_changed')->sole();
        $this->assertEquals(['from' => 'Ditinjau', 'to' => 'Lolos seleksi awal'], $log->properties);

        // Dari tahap terakhir, langkah berikutnya adalah keputusan "Diterima".
        $this->move($this->application, 'hired')->assertSessionHasNoErrors();
        $this->assertSame(RecruitmentStage::Hired, $this->application->refresh()->stage);
    }

    public function test_stages_cannot_be_skipped()
    {
        $this->actingAs($this->hr);

        // Dari tahap 1 tidak bisa langsung diterima (masih ada tahap 2).
        $this->move($this->application, 'hired')->assertSessionHasErrors('target');
        $this->move($this->application, 'tidak-ada')->assertSessionHasErrors('target');

        // Terdaftar → hanya ke tahap proses pertama.
        $registered = RecruitmentApplication::factory()->for($this->application->campaign, 'campaign')->stage(RecruitmentStage::Registered)->create();
        $this->move($registered, 'stage:'.$this->stage(2)->public_id)->assertSessionHasErrors('target');
        $this->move($registered, 'stage:'.$this->stage(1)->public_id)->assertSessionHasNoErrors();

        // Diundang → terdaftar hanya lewat aktivasi akun.
        $invited = RecruitmentApplication::factory()->for($this->application->campaign, 'campaign')->create();
        $this->move($invited, 'registered')->assertSessionHasErrors('target');

        // Tahap rekrutmen lain tidak bisa dipakai.
        $foreign = RecruitmentCampaign::factory()->create()->stages()->first();
        $this->move($this->application, 'stage:'.$foreign->public_id)->assertSessionHasErrors('target');

        $this->assertSame($this->stage(1)->id, $this->application->refresh()->campaign_stage_id);
    }

    public function test_rejection_requires_a_reason()
    {
        $this->actingAs($this->hr);

        $this->move($this->application, 'rejected', '  ')->assertSessionHasErrors('reason');
        $this->move($this->application, 'rejected', 'Tidak hadir wawancara')->assertSessionHasNoErrors();

        $this->application->refresh();
        $this->assertSame(RecruitmentStage::Rejected, $this->application->stage);
        // Tahap tempat keputusan diambil tetap tersimpan.
        $this->assertSame($this->stage(1)->id, $this->application->campaign_stage_id);
        $this->assertSame('Tidak hadir wawancara', $this->application->stageHistories()->sole()->reason);
    }

    public function test_final_decisions_need_the_decide_permission()
    {
        $interviewer = User::factory()->interviewer()->create();
        $this->application->campaign->hrUsers()->attach($interviewer->id);
        $role = JobRole::create(['name' => 'Koordinator']);
        $role->syncPermissions([Permission::ApplicationsMoveStage]);
        $coordinator = User::factory()->withJobRole($role)->create();
        $this->application->campaign->hrUsers()->attach($coordinator->id);

        $this->actingAs($interviewer);
        $this->move($this->application, 'stage:'.$this->stage(2)->public_id)->assertForbidden();

        $this->actingAs($coordinator);
        $this->move($this->application, 'rejected', 'Tidak cocok')->assertSessionHasErrors('target');
        $this->move($this->application, 'stage:'.$this->stage(2)->public_id)->assertSessionHasNoErrors();
        $this->move($this->application, 'hired')->assertSessionHasErrors('target');
    }

    public function test_only_super_admin_can_correct_a_rejection_or_change_closed_campaigns()
    {
        $this->actingAs($this->hr)->move($this->application, 'rejected', 'Salah pilih');
        $admin = User::factory()->superAdmin()->create();
        $back = 'stage:'.$this->stage(1)->public_id;

        $this->move($this->application, $back, 'Salah input')->assertSessionHasErrors('target');

        $this->actingAs($admin);
        $this->move($this->application, $back)->assertSessionHasErrors('reason');
        $this->move($this->application, $back, 'Salah input')->assertSessionHasNoErrors();
        $this->assertSame(RecruitmentStage::InProcess, $this->application->refresh()->stage);

        $this->application->campaign->update(['status' => 'closed']);

        $this->actingAs($this->hr);
        $this->move($this->application, 'stage:'.$this->stage(2)->public_id)->assertSessionHasErrors('target');

        $this->actingAs($admin);
        $this->move($this->application, 'stage:'.$this->stage(2)->public_id)->assertSessionHasNoErrors();
    }

    public function test_the_ui_only_offers_the_next_step()
    {
        $this->actingAs($this->hr)
            ->get(route('admin.candidates.show', $this->application->candidateProfile))
            ->assertInertia(fn (Assert $page) => $page
                ->where('candidate.applications.0.stage_label', 'Ditinjau')
                ->where('candidate.applications.0.pipeline.0.is_current', true)
                ->where('candidate.applications.0.stage_options.0.target', 'stage:'.$this->stage(2)->public_id)
                ->where('candidate.applications.0.stage_options.0.label', 'Lolos seleksi awal')
                ->where('candidate.applications.0.stage_options.1.target', 'rejected')
                ->where('candidate.applications.0.stage_options.2.target', 'withdrawn')
                ->has('candidate.applications.0.stage_options', 3));
    }

    public function test_out_of_scope_applications_return_not_found()
    {
        $other = RecruitmentApplication::factory()->inProcess()->create();
        $this->actingAs($this->hr);

        $this->move($other, 'withdrawn', 'x')->assertNotFound();
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
