<?php

namespace Tests\Feature\Access;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Shared\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidates_without_consent_are_redirected_to_the_consent_page()
    {
        $candidate = CandidateProfile::factory()->create(['activated_at' => now()])->user;

        $this->actingAs($candidate)->get(route('dashboard'))->assertRedirect(route('consent.show'));
        $this->actingAs($candidate)->get(route('profile.edit'))->assertRedirect(route('consent.show'));
        $this->actingAs($candidate)->get(route('consent.show'))->assertOk();
    }

    public function test_consent_must_be_explicitly_accepted()
    {
        $profile = CandidateProfile::factory()->create();

        $this->actingAs($profile->user)
            ->post(route('consent.store'), ['accepted' => false])
            ->assertSessionHasErrors('accepted');

        $this->assertNull($profile->refresh()->consent_given_at);
    }

    public function test_accepting_records_the_time_and_policy_version()
    {
        $profile = CandidateProfile::factory()->create();

        $this->actingAs($profile->user)
            ->post(route('consent.store'), ['accepted' => true])
            ->assertRedirect(route('dashboard'));

        $profile->refresh();
        $this->assertNotNull($profile->consent_given_at);
        $this->assertSame('2026-10', $profile->consent_policy_version);
        $this->actingAs($profile->user)->get(route('dashboard'))->assertOk();
    }

    public function test_a_new_policy_version_requires_consent_again()
    {
        $profile = CandidateProfile::factory()->activated('2026-10')->create();
        SystemSetting::put(SystemSetting::CONSENT_POLICY_VERSION, '2027-01');

        $this->actingAs($profile->user)
            ->get(route('dashboard'))
            ->assertRedirect(route('consent.show'));

        $this->actingAs($profile->user)
            ->get(route('consent.show'))
            ->assertInertia(fn ($page) => $page
                ->where('policyVersion', '2027-01')
                ->where('previouslyAccepted', true));
    }

    public function test_staff_never_see_the_consent_page()
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($hr)->get(route('consent.show'))->assertNotFound();
    }
}
