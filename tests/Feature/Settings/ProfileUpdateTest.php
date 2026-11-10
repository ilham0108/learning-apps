<?php

namespace Tests\Feature\Settings;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->hr()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->hr()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->hr()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_candidates_can_update_their_name_but_not_their_email()
    {
        $user = CandidateProfile::factory()->activated()->create()->user;
        $originalEmail = $user->email;

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Nama Baru'])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Nama Baru', 'email' => 'lain@example.com'])
            ->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame($originalEmail, $user->email);
    }

    public function test_self_service_account_deletion_is_not_available()
    {
        $user = User::factory()->hr()->create();

        $this->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertModelExists($user);
    }
}
