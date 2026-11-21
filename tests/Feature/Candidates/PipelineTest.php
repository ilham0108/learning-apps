<?php

namespace Tests\Feature\Candidates;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\PipelineStageTemplate;
use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsNoInternalIds;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use AssertsNoInternalIds, RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function defaultNames(): array
    {
        return PipelineStageTemplate::query()->orderBy('position')->pluck('name')->all();
    }

    public function test_head_hr_manages_the_default_pipeline()
    {
        $this->actingAs(User::factory()->headHr()->create());

        $this->post(route('admin.pipeline.stages.store'), ['name' => 'Psikotes'])->assertSessionHasNoErrors();
        $this->post(route('admin.pipeline.stages.store'), ['name' => 'psikotes'])->assertSessionHasErrors('name');
        $this->assertSame(['Ditinjau', 'Lolos seleksi awal', 'Psikotes'], $this->defaultNames());

        $psikotes = PipelineStageTemplate::query()->where('name', 'Psikotes')->sole();
        $this->post(route('admin.pipeline.stages.move', $psikotes), ['direction' => 'up'])->assertRedirect();
        $this->patch(route('admin.pipeline.stages.update', PipelineStageTemplate::query()->where('name', 'Ditinjau')->sole()), ['name' => 'Seleksi berkas'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Seleksi berkas', 'Psikotes', 'Lolos seleksi awal'], $this->defaultNames());

        $this->delete(route('admin.pipeline.stages.destroy', PipelineStageTemplate::query()->where('name', 'Lolos seleksi awal')->sole()))
            ->assertRedirect();
        $this->assertSame(['Seleksi berkas', 'Psikotes'], $this->defaultNames());
        $this->assertSame([1, 2], PipelineStageTemplate::query()->orderBy('position')->pluck('position')->all());
        $this->assertTrue(ActivityLog::query()->where('action', 'pipeline.default_added')->exists());

        $response = $this->get(route('admin.pipeline.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/pipeline/index')->has('stages', 2));
        $this->assertResponseHasNoInternalIds($response);
    }

    public function test_recruiters_cannot_change_the_default_pipeline()
    {
        $this->actingAs(User::factory()->hr()->create());

        $this->get(route('admin.pipeline.index'))->assertNotFound();
        $this->post(route('admin.pipeline.stages.store'), ['name' => 'X'])->assertNotFound();
    }

    public function test_new_campaigns_copy_the_default_and_keep_their_own_copy()
    {
        $hr = User::factory()->hr()->create();
        PipelineStageTemplate::query()->create(['name' => 'Wawancara user', 'position' => 3]);

        $this->actingAs($hr)->post(route('admin.campaigns.store'), [
            'name' => 'Sales Desember',
            'position' => Position::factory()->create()->public_id,
            'starts_on' => now()->toDateString(),
            'status' => 'open',
        ])->assertSessionHasNoErrors();

        $campaign = RecruitmentCampaign::query()->where('name', 'Sales Desember')->sole();
        $this->assertSame(['Ditinjau', 'Lolos seleksi awal', 'Wawancara user'], $campaign->stages()->pluck('name')->all());

        // Perubahan default berikutnya tidak memengaruhi rekrutmen yang sudah ada.
        PipelineStageTemplate::query()->where('name', 'Wawancara user')->delete();
        $this->assertSame(3, $campaign->stages()->count());
    }

    public function test_recruiters_adjust_the_pipeline_of_their_own_campaign()
    {
        $hr = User::factory()->hr()->create();
        $campaign = RecruitmentCampaign::factory()->assignedTo($hr)->create();
        $used = $campaign->stages()->where('position', 1)->sole();
        RecruitmentApplication::factory()->for($campaign, 'campaign')->inProcess(1)->create();

        $this->actingAs($hr);
        $this->post(route('admin.campaigns.stages.store', $campaign), ['name' => 'Tes coding'])->assertSessionHasNoErrors();
        $this->assertSame(['Ditinjau', 'Lolos seleksi awal', 'Tes coding'], $campaign->stages()->pluck('name')->all());

        // Tahap yang sudah dipakai kandidat tidak dapat dihapus, tetapi bisa diganti nama.
        $this->delete(route('admin.campaigns.stages.destroy', [$campaign, $used]))->assertSessionHasErrors('stage');
        $this->patch(route('admin.campaigns.stages.update', [$campaign, $used]), ['name' => 'Seleksi berkas'])->assertSessionHasNoErrors();

        $this->get(route('admin.campaigns.show', $campaign))->assertInertia(fn (Assert $page) => $page
            ->where('canManagePipeline', true)
            ->where('pipeline.0.name', 'Seleksi berkas')
            ->where('pipeline.0.current_count', 1)
            ->where('pipeline.0.removable', false)
            ->where('pipeline.2.removable', true));

        // Tahap rekrutmen lain dan rekrutmen di luar lingkup tidak dapat diubah.
        $other = RecruitmentCampaign::factory()->create();
        $this->post(route('admin.campaigns.stages.store', $other), ['name' => 'X'])->assertNotFound();
        $this->patch(route('admin.campaigns.stages.update', [$campaign, $other->stages()->first()]), ['name' => 'X'])->assertNotFound();

        // Interviewer di rekrutmen yang sama hanya dapat melihat.
        $interviewer = User::factory()->interviewer()->create();
        $campaign->hrUsers()->attach($interviewer->id);
        $this->actingAs($interviewer)->post(route('admin.campaigns.stages.store', $campaign), ['name' => 'Y'])->assertForbidden();
    }

    public function test_candidates_see_their_position_in_the_campaign_pipeline()
    {
        $profile = CandidateProfile::factory()->activated()->create();
        $application = RecruitmentApplication::factory()->for($profile)->inProcess(2)->create();

        $this->actingAs($profile->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('applications.0.stage', 'in_process')
            ->where('applications.0.stage_label', 'Lolos seleksi awal')
            ->where('applications.0.pipeline', ['Ditinjau', 'Lolos seleksi awal'])
            ->where('applications.0.current_step', 1));

        $this->assertSame('Lolos seleksi awal', $application->fresh()->stageLabel());
    }
}
