<?php

namespace Tests\Feature\Candidates;

use App\Domain\Candidates\Enums\CampaignStatus;
use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CampaignManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Sales — Januari 2027',
            'position' => Position::factory()->create()->public_id,
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-02-28',
            'status' => 'open',
            ...$overrides,
        ];
    }

    public function test_hr_who_creates_a_campaign_is_assigned_to_it()
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->post(route('admin.campaigns.store'), $this->payload())->assertSessionHasNoErrors();

        $campaign = RecruitmentCampaign::query()->sole();
        $this->assertSame($hr->id, $campaign->created_by);
        $this->assertTrue($campaign->hrUsers()->whereKey($hr->id)->exists());
        $this->assertTrue(ActivityLog::query()->where('action', 'campaign.created')->exists());
    }

    public function test_super_admin_created_campaigns_have_no_team_yet()
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admin.campaigns.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(0, RecruitmentCampaign::query()->sole()->hrUsers()->count());
    }

    public function test_campaign_validation()
    {
        $this->actingAs(User::factory()->hr()->create());

        $this->post(route('admin.campaigns.store'), $this->payload(['ends_on' => '2026-12-01']))
            ->assertSessionHasErrors('ends_on');
        $this->post(route('admin.campaigns.store'), $this->payload(['position' => 'bukan-ulid']))
            ->assertSessionHasErrors('position');
        $this->post(route('admin.campaigns.store'), $this->payload([
            'position' => Position::factory()->create(['is_active' => false])->public_id,
        ]))->assertSessionHasErrors('position');
        $this->post(route('admin.campaigns.store'), $this->payload(['status' => 'archived']))
            ->assertSessionHasErrors('status');
    }

    public function test_hr_only_lists_and_opens_assigned_campaigns()
    {
        $hr = User::factory()->hr()->create();
        $mine = RecruitmentCampaign::factory()->assignedTo($hr)->create();
        $other = RecruitmentCampaign::factory()->create();

        $this->actingAs($hr)
            ->get(route('admin.campaigns.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('campaigns.data', 1)
                ->where('campaigns.data.0.id', $mine->public_id));

        $this->get(route('admin.campaigns.show', $mine))->assertOk();
        $this->get(route('admin.campaigns.show', $other))->assertNotFound();
        $this->put(route('admin.campaigns.update', $other), $this->payload())->assertNotFound();
    }

    public function test_form_modals_receive_position_options()
    {
        $hr = User::factory()->hr()->create();
        $active = Position::factory()->create();
        Position::factory()->create(['is_active' => false]);
        $campaign = RecruitmentCampaign::factory()->assignedTo($hr)->create();

        $this->actingAs($hr)
            ->get(route('admin.campaigns.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCreate', true)
                ->where('positions', fn ($positions) => collect($positions)->pluck('id')->contains($active->public_id)
                    && collect($positions)->count() === 2));

        $this->get(route('admin.campaigns.show', $campaign))
            ->assertInertia(fn (Assert $page) => $page->where('canUpdate', true)->has('positions', 2));

        // Form tambah/ubah berupa modal; tidak ada halaman terpisah.
        $this->get('/admin/campaigns/create')->assertNotFound();
        $this->get("/admin/campaigns/{$campaign->public_id}/edit")->assertNotFound();
    }

    public function test_closing_a_campaign_records_the_time_and_audit_log()
    {
        $hr = User::factory()->hr()->create();
        $campaign = RecruitmentCampaign::factory()->assignedTo($hr)->create();

        $this->actingAs($hr)
            ->put(route('admin.campaigns.update', $campaign), $this->payload([
                'position' => $campaign->position->public_id,
                'status' => 'closed',
            ]))
            ->assertRedirect(route('admin.campaigns.show', $campaign));

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Closed, $campaign->status);
        $this->assertNotNull($campaign->closed_at);

        $log = ActivityLog::query()->where('action', 'campaign.status_changed')->sole();
        $this->assertEquals(['from' => 'open', 'to' => 'closed'], $log->properties);
    }

    public function test_only_super_admin_manages_the_campaign_team()
    {
        $hr = User::factory()->hr()->create();
        $other = User::factory()->hr()->create();
        $campaign = RecruitmentCampaign::factory()->assignedTo($hr)->create();

        $this->actingAs($hr)
            ->put(route('admin.campaigns.team.update', $campaign), ['hr_users' => [$hr->public_id, $other->public_id]])
            ->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('admin.campaigns.team.update', $campaign), ['hr_users' => [$other->public_id]])
            ->assertSessionHasNoErrors();

        $this->assertEquals([$other->id], $campaign->hrUsers()->pluck('users.id')->all());

        $log = ActivityLog::query()->where('action', 'campaign.team_changed')->sole();
        $this->assertSame([$other->public_id], $log->properties['added']);
        $this->assertSame([$hr->public_id], $log->properties['removed']);
    }

    public function test_only_active_hr_accounts_can_be_assigned()
    {
        $campaign = RecruitmentCampaign::factory()->create();
        $this->actingAs(User::factory()->superAdmin()->create());

        foreach ([User::factory()->hr()->inactive()->create(), User::factory()->candidate()->create()] as $user) {
            $this->put(route('admin.campaigns.team.update', $campaign), ['hr_users' => [$user->public_id]])
                ->assertSessionHasErrors('hr_users.0');
        }
    }
}
