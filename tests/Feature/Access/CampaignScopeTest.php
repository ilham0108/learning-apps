<?php

namespace Tests\Feature\Access;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Lingkup data HR per gelombang (docs/tahap-1.md bagian 9). Data di luar
 * lingkup harus ditolak sebagai 404, bukan 403.
 */
class CampaignScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private RecruitmentApplication $inScope;

    private RecruitmentApplication $outOfScope;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->hr()->create();
        $this->inScope = RecruitmentApplication::factory()
            ->for(RecruitmentCampaign::factory()->assignedTo($this->hr), 'campaign')
            ->create();
        $this->outOfScope = RecruitmentApplication::factory()->create();
    }

    public function test_hr_query_scopes_only_return_assigned_campaign_data()
    {
        $this->assertEquals(
            [$this->inScope->recruitment_campaign_id],
            RecruitmentCampaign::query()->visibleTo($this->hr)->pluck('id')->all(),
        );
        $this->assertEquals(
            [$this->inScope->id],
            RecruitmentApplication::query()->visibleTo($this->hr)->pluck('id')->all(),
        );
        $this->assertEquals(
            [$this->inScope->candidate_profile_id],
            CandidateProfile::query()->visibleTo($this->hr)->pluck('id')->all(),
        );
    }

    public function test_hr_policies_allow_in_scope_and_hide_out_of_scope_data()
    {
        $gate = Gate::forUser($this->hr);

        $this->assertTrue($gate->allows('view', $this->inScope->campaign));
        $this->assertTrue($gate->allows('manage', $this->inScope));
        $this->assertTrue($gate->allows('view', $this->inScope->candidateProfile));

        $denied = $gate->inspect('view', $this->outOfScope->campaign);
        $this->assertTrue($denied->denied());
        $this->assertSame(404, $denied->status());

        $this->assertSame(404, $gate->inspect('manage', $this->outOfScope)->status());
        $this->assertSame(404, $gate->inspect('view', $this->outOfScope->candidateProfile)->status());
    }

    public function test_a_candidate_in_two_campaigns_only_exposes_the_in_scope_application()
    {
        $profile = $this->outOfScope->candidateProfile;
        $shared = RecruitmentApplication::factory()->for($profile)->for($this->inScope->campaign, 'campaign')->create();

        $this->assertTrue(Gate::forUser($this->hr)->allows('view', $profile));
        $this->assertEquals(
            [$this->inScope->id, $shared->id],
            RecruitmentApplication::query()->visibleTo($this->hr)->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_only_super_admin_can_manage_the_campaign_team()
    {
        $campaign = $this->inScope->campaign;

        $this->assertTrue(Gate::forUser(User::factory()->superAdmin()->create())->allows('manageTeam', $campaign));

        // HR yang ditugaskan tahu gelombangnya ada, jadi ditolak dengan 403 biasa.
        $denied = Gate::forUser($this->hr)->inspect('manageTeam', $campaign);
        $this->assertTrue($denied->denied());
        $this->assertNull($denied->status());
    }

    public function test_super_admin_sees_everything()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->assertSame(2, RecruitmentApplication::query()->visibleTo($admin)->count());
        $this->assertTrue(Gate::forUser($admin)->allows('view', $this->outOfScope->campaign));
    }

    public function test_candidates_only_see_their_own_applications()
    {
        $candidate = $this->inScope->candidateProfile->user;
        $gate = Gate::forUser($candidate);

        $this->assertEquals([$this->inScope->id], RecruitmentApplication::query()->visibleTo($candidate)->pluck('id')->all());
        $this->assertTrue($gate->allows('view', $this->inScope));
        $this->assertSame(404, $gate->inspect('view', $this->outOfScope)->status());
        $this->assertTrue($gate->denies('manage', $this->inScope));
        $this->assertSame(0, RecruitmentCampaign::query()->visibleTo($candidate)->count());
        $this->assertTrue($gate->denies('viewAny', RecruitmentCampaign::class));
    }
}
