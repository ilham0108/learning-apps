<?php

namespace Tests\Feature\Access;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidates_get_a_not_found_page_for_admin_routes()
    {
        $candidate = CandidateProfile::factory()->activated()->create()->user;

        $this->actingAs($candidate)
            ->get(route('admin.dashboard'))
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error')
                ->where('status', 404));
    }

    public function test_super_admin_and_hr_can_open_the_admin_area()
    {
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.dashboard'))->assertOk();
        $this->actingAs(User::factory()->hr()->create())->get(route('admin.dashboard'))->assertOk();
    }

    public function test_unknown_pages_render_the_indonesian_error_page()
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 404));
    }

    public function test_inactive_users_cannot_log_in()
    {
        $user = User::factory()->hr()->inactive()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_deactivated_during_a_session_are_logged_out()
    {
        $user = User::factory()->hr()->create();
        $this->actingAs($user);

        $user->forceFill(['is_active' => false])->save();

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_successful_login_records_the_last_login_time()
    {
        $user = User::factory()->hr()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_two_factor_settings_are_only_offered_to_super_admin_and_hr()
    {
        $candidate = CandidateProfile::factory()->activated()->create()->user;

        $this->actingAs($candidate)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageTwoFactor', false)
                ->missing('twoFactorEnabled'));

        $this->actingAs(User::factory()->hr()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('canManageTwoFactor', true));
    }

    public function test_candidates_cannot_change_their_email_on_the_profile_page()
    {
        $candidate = CandidateProfile::factory()->activated()->create()->user;

        $this->actingAs($candidate)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('canChangeEmail', false));
    }
}
