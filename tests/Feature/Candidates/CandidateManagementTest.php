<?php

namespace Tests\Feature\Candidates;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CandidateManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private RecruitmentCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->hr()->create();
        $this->campaign = RecruitmentCampaign::factory()->assignedTo($this->hr)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Siti Rahma',
            'email' => 'siti@example.com',
            'phone' => '081234567890',
            'last_education' => 'S1 Manajemen',
            'campaign' => $this->campaign->public_id,
            ...$overrides,
        ];
    }

    public function test_adding_a_new_candidate_creates_account_profile_and_invited_application()
    {
        $this->actingAs($this->hr)->post(route('admin.candidates.store'), $this->payload())->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'siti@example.com')->sole();
        $this->assertSame(Role::Candidate, $user->role);

        $profile = $user->candidateProfile;
        $this->assertSame(sprintf('CAND-%d-000001', now()->year), $profile->candidate_number);
        $this->assertNull($profile->activated_at);

        $application = $profile->applications()->sole();
        $this->assertSame(RecruitmentStage::Invited, $application->stage);
        $this->assertSame($this->hr->id, $application->stageHistories()->sole()->changed_by);
        $this->assertTrue(ActivityLog::query()->where('action', 'application.created')->exists());
    }

    public function test_candidate_numbers_are_sequential()
    {
        $this->actingAs($this->hr);
        $this->post(route('admin.candidates.store'), $this->payload());
        $this->post(route('admin.candidates.store'), $this->payload(['email' => 'kedua@example.com']));

        $this->assertSame(
            [sprintf('CAND-%d-000001', now()->year), sprintf('CAND-%d-000002', now()->year)],
            CandidateProfile::query()->orderBy('id')->pluck('candidate_number')->all(),
        );
    }

    public function test_an_existing_candidate_email_adds_an_application_instead_of_a_new_account()
    {
        $existing = CandidateProfile::factory()->activated()->create();

        $this->actingAs($this->hr)
            ->post(route('admin.candidates.store'), $this->payload(['email' => strtoupper($existing->user->email)]))
            ->assertRedirect(route('admin.candidates.show', $existing));

        $this->assertSame(1, CandidateProfile::query()->count());
        $application = $existing->applications()->sole();
        // Akun sudah aktif, jadi langsung terdaftar.
        $this->assertSame(RecruitmentStage::Registered, $application->stage);

        $this->post(route('admin.candidates.store'), $this->payload(['email' => $existing->user->email]))
            ->assertSessionHasErrors('email');
    }

    public function test_staff_emails_closed_and_out_of_scope_campaigns_are_rejected()
    {
        $this->actingAs($this->hr);

        $this->post(route('admin.candidates.store'), $this->payload(['email' => $this->hr->email]))
            ->assertSessionHasErrors('email');
        $this->post(route('admin.candidates.store'), $this->payload([
            'campaign' => RecruitmentCampaign::factory()->closed()->assignedTo($this->hr)->create()->public_id,
        ]))->assertSessionHasErrors('campaign');
        $this->post(route('admin.candidates.store'), $this->payload([
            'campaign' => RecruitmentCampaign::factory()->create()->public_id,
        ]))->assertSessionHasErrors('campaign');
        $this->post(route('admin.candidates.store'), $this->payload(['phone' => 'abc']))
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, CandidateProfile::query()->count());
    }

    public function test_add_candidate_modal_only_offers_open_campaigns_in_scope()
    {
        RecruitmentCampaign::factory()->closed()->assignedTo($this->hr)->create();
        RecruitmentCampaign::factory()->create();

        $this->actingAs($this->hr)
            ->get(route('admin.candidates.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('openCampaignOptions', 1)
                ->where('openCampaignOptions.0.id', $this->campaign->public_id)
                ->has('campaignOptions', 2));
    }

    public function test_index_is_scoped_searchable_filterable_and_paginated()
    {
        $inScope = RecruitmentApplication::factory()->count(16)->for($this->campaign, 'campaign')->create();
        RecruitmentApplication::factory()->count(3)->create();
        $target = $inScope->first();
        $target->candidateProfile->user->update(['name' => 'Zzqx Vortanen']);
        $target->update(['stage' => RecruitmentStage::Shortlisted]);

        $this->actingAs($this->hr);

        $this->get(route('admin.candidates.index'))->assertInertia(fn (Assert $page) => $page
            ->has('candidates.data', 15)
            ->where('candidates.meta.total', 16)
            ->where('candidates.meta.last_page', 2));

        $this->get(route('admin.candidates.index', ['search' => 'zzqx vort']))->assertInertia(fn (Assert $page) => $page
            ->has('candidates.data', 1)
            ->where('candidates.data.0.id', $target->candidateProfile->public_id));

        $this->get(route('admin.candidates.index', ['stage' => 'shortlisted']))->assertInertia(fn (Assert $page) => $page
            ->where('candidates.meta.total', 1));

        $this->get(route('admin.candidates.index', ['sort' => 'name', 'direction' => 'desc']))->assertInertia(fn (Assert $page) => $page
            ->where('candidates.data.0.name', 'Zzqx Vortanen'));

        // Gelombang di luar lingkup tidak dapat dipakai sebagai filter.
        $this->get(route('admin.candidates.index', ['campaign' => RecruitmentCampaign::factory()->create()->public_id]))
            ->assertSessionHasErrors('campaign');
        $this->get(route('admin.candidates.index', ['sort' => 'password']))->assertSessionHasErrors('sort');
    }

    public function test_search_treats_wildcards_literally()
    {
        RecruitmentApplication::factory()->count(2)->for($this->campaign, 'campaign')->create();

        $this->actingAs($this->hr)
            ->get(route('admin.candidates.index', ['search' => '%']))
            ->assertInertia(fn (Assert $page) => $page->where('candidates.meta.total', 0));
    }

    public function test_detail_page_only_shows_applications_in_scope()
    {
        $profile = RecruitmentApplication::factory()->for($this->campaign, 'campaign')->create()->candidateProfile;
        RecruitmentApplication::factory()->for($profile)->create();

        $this->actingAs($this->hr)
            ->get(route('admin.candidates.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/candidates/show')
                ->has('candidate.applications', 1)
                ->where('candidate.applications.0.campaign.id', $this->campaign->public_id));

        $this->get(route('admin.candidates.show', CandidateProfile::factory()->create()))->assertNotFound();
    }

    public function test_hr_can_update_candidate_data_and_changes_are_audited()
    {
        $profile = RecruitmentApplication::factory()->for($this->campaign, 'campaign')->create()->candidateProfile;

        $this->actingAs($this->hr)
            ->put(route('admin.candidates.update', $profile), [
                'name' => 'Nama Baru',
                'email' => 'baru@example.com',
                'phone' => '+62 812-3456-7890',
                'last_education' => 'S3 Ekonomi',
            ])
            ->assertSessionHasNoErrors();

        $profile->refresh();
        $this->assertSame('Nama Baru', $profile->user->name);
        $this->assertSame('+62 812-3456-7890', $profile->phone);
        $this->assertEqualsCanonicalizing(
            ['name', 'email', 'phone', 'last_education'],
            ActivityLog::query()->where('action', 'candidate.updated')->sole()->properties['fields'],
        );
    }

    public function test_deactivation_requires_a_reason_and_blocks_login()
    {
        $profile = RecruitmentApplication::factory()->for($this->campaign, 'campaign')->create()->candidateProfile;
        $this->actingAs($this->hr);

        $this->patch(route('admin.candidates.status.update', $profile), ['active' => false])
            ->assertSessionHasErrors('reason');

        $this->patch(route('admin.candidates.status.update', $profile), ['active' => false, 'reason' => 'Data ganda'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($profile->user->refresh()->is_active);
        $this->assertSame('Data ganda', ActivityLog::query()->where('action', 'candidate.deactivated')->sole()->reason);

        auth()->logout();
        $this->post(route('login.store'), ['email' => $profile->user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
    }

    public function test_activation_link_is_flashed_once_for_unactivated_candidates()
    {
        $profile = RecruitmentApplication::factory()->for($this->campaign, 'campaign')->create()->candidateProfile;

        $this->actingAs($this->hr)
            ->post(route('admin.candidates.activation-link.store', $profile))
            ->assertInertiaFlash('activationLink');

        // Browser memuat halaman setelah redirect; flash terpakai dan tidak muncul lagi.
        $this->get(route('admin.candidates.show', $profile))->assertOk();

        $activated = RecruitmentApplication::factory()
            ->for(CandidateProfile::factory()->activated(), 'candidateProfile')
            ->for($this->campaign, 'campaign')
            ->create()
            ->candidateProfile;

        $this->post(route('admin.candidates.activation-link.store', $activated))
            ->assertInertiaFlashMissing('activationLink');
    }

    public function test_out_of_scope_candidates_cannot_be_changed()
    {
        $profile = CandidateProfile::factory()->create();
        $this->actingAs($this->hr);

        $this->put(route('admin.candidates.update', $profile), ['name' => 'X', 'email' => 'x@example.com'])->assertNotFound();
        $this->patch(route('admin.candidates.status.update', $profile), ['active' => false, 'reason' => 'x'])->assertNotFound();
        $this->post(route('admin.candidates.activation-link.store', $profile))->assertNotFound();

        $this->assertTrue($profile->user->refresh()->is_active);
    }
}
