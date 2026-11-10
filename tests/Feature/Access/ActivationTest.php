<?php

namespace Tests\Feature\Access;

use App\Domain\Access\Actions\CreateActivationLink;
use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class ActivationTest extends TestCase
{
    use RefreshDatabase;

    private function invitedApplication(): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create(['stage' => RecruitmentStage::Invited]);
    }

    private function createLink(User $candidate): string
    {
        $this->actingAs(User::factory()->hr()->create());
        $url = app(CreateActivationLink::class)($candidate);
        auth()->logout();

        return $url;
    }

    public function test_the_activation_page_shows_for_a_valid_link()
    {
        $candidate = $this->invitedApplication()->candidateProfile->user;
        $url = $this->createLink($candidate);

        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->component('auth/activate')
            ->where('userId', $candidate->public_id)
            ->where('email', $candidate->email));
    }

    public function test_the_link_uses_the_public_id_and_never_the_email()
    {
        $candidate = $this->invitedApplication()->candidateProfile->user;
        $url = $this->createLink($candidate);

        $this->assertStringContainsString($candidate->public_id, $url);
        $this->assertStringNotContainsString(urlencode($candidate->email), $url);
        $this->assertStringNotContainsString('/'.$candidate->id.'/', $url);
    }

    public function test_activation_sets_the_password_registers_invited_applications_and_logs_in()
    {
        $application = $this->invitedApplication();
        $candidate = $application->candidateProfile->user;
        $url = $this->createLink($candidate);

        $this->post($url, ['password' => 'rahasia-baru-123', 'password_confirmation' => 'rahasia-baru-123'])
            ->assertRedirect(route('consent.show'));

        $this->assertAuthenticatedAs($candidate);
        $this->assertTrue(Hash::check('rahasia-baru-123', $candidate->refresh()->password));
        $this->assertNotNull($candidate->candidateProfile->activated_at);

        $application->refresh();
        $this->assertSame(RecruitmentStage::Registered, $application->stage);

        $history = $application->stageHistories()->sole();
        $this->assertSame(RecruitmentStage::Invited, $history->from_stage);
        $this->assertSame(RecruitmentStage::Registered, $history->to_stage);
        $this->assertNull($history->changed_by);
    }

    public function test_an_activation_link_can_only_be_used_once()
    {
        $candidate = $this->invitedApplication()->candidateProfile->user;
        $url = $this->createLink($candidate);

        $this->post($url, ['password' => 'rahasia-baru-123', 'password_confirmation' => 'rahasia-baru-123']);
        auth()->logout();

        $this->get($url)->assertNotFound();
        $this->post($url, ['password' => 'lain-lagi-456', 'password_confirmation' => 'lain-lagi-456'])->assertNotFound();
    }

    public function test_an_activation_link_expires_after_72_hours()
    {
        $candidate = $this->invitedApplication()->candidateProfile->user;
        $url = $this->createLink($candidate);

        $this->travel(73)->hours();

        $this->get($url)->assertNotFound();
    }

    public function test_a_tampered_token_is_rejected()
    {
        $candidate = $this->invitedApplication()->candidateProfile->user;
        $this->createLink($candidate);

        $this->get(route('activation.show', ['user' => $candidate, 'token' => 'token-palsu']))->assertNotFound();
    }

    public function test_a_new_link_invalidates_the_previous_one()
    {
        $candidate = $this->invitedApplication()->candidateProfile->user;
        $first = $this->createLink($candidate);
        $second = $this->createLink($candidate);

        $this->get($first)->assertNotFound();
        $this->get($second)->assertOk();
    }

    public function test_links_cannot_be_created_for_staff_or_already_activated_candidates()
    {
        $this->actingAs(User::factory()->hr()->create());

        $this->expectException(InvalidArgumentException::class);
        app(CreateActivationLink::class)(CandidateProfile::factory()->activated()->create()->user);
    }

    public function test_links_cannot_be_created_for_staff_accounts()
    {
        $this->actingAs(User::factory()->hr()->create());

        $this->expectException(InvalidArgumentException::class);
        app(CreateActivationLink::class)(User::factory()->superAdmin()->create());
    }

    public function test_creating_a_link_is_written_to_the_audit_log()
    {
        $candidate = $this->invitedApplication()->candidateProfile->user;
        $this->createLink($candidate);

        $log = ActivityLog::query()->sole();
        $this->assertSame('candidate.activation_link_created', $log->action);
        $this->assertSame('candidate_profile', $log->subject_type);
        $this->assertNotNull($log->actor_id);
    }
}
