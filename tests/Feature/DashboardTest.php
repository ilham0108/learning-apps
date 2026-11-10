<?php

namespace Tests\Feature;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_url_redirects_to_the_dashboard()
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_candidates_see_their_own_applications()
    {
        $profile = CandidateProfile::factory()->activated()->create();
        RecruitmentApplication::factory()->for($profile)->create();
        RecruitmentApplication::factory()->create();

        $this->actingAs($profile->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/dashboard')
                ->where('candidateNumber', $profile->candidate_number)
                ->has('applications', 1));
    }

    public function test_super_admin_and_hr_are_sent_to_the_admin_dashboard()
    {
        foreach ([User::factory()->superAdmin()->create(), User::factory()->hr()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertRedirect(route('admin.dashboard'));
        }
    }

    public function test_hr_only_sees_assigned_campaigns()
    {
        $hr = User::factory()->hr()->create();
        $assigned = RecruitmentCampaign::factory()->assignedTo($hr)->create();
        RecruitmentApplication::factory()->count(2)->for($assigned, 'campaign')->create();
        RecruitmentApplication::factory()->count(3)->create();

        $this->actingAs($hr)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dashboard')
                ->has('campaigns', 1)
                ->where('campaigns.0.id', $assigned->public_id)
                ->where('campaigns.0.applications_count', 2)
                ->where('candidateCount', 2));
    }

    public function test_super_admin_sees_all_campaigns()
    {
        RecruitmentApplication::factory()->count(3)->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('campaigns', 3)
                ->where('candidateCount', 3));
    }
}
