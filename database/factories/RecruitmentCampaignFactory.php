<?php

namespace Database\Factories;

use App\Domain\Candidates\Enums\CampaignStatus;
use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecruitmentCampaign>
 */
class RecruitmentCampaignFactory extends Factory
{
    protected $model = RecruitmentCampaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = now()->subDays(fake()->numberBetween(0, 30))->startOfDay();

        return [
            'name' => 'Gelombang '.fake()->unique()->numberBetween(1, 9999),
            'position_id' => Position::factory(),
            'starts_on' => $startsOn,
            'ends_on' => $startsOn->addDays(60),
            'status' => CampaignStatus::Open,
            'created_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CampaignStatus::Draft,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CampaignStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    /**
     * Tugaskan HR ke gelombang setelah dibuat.
     */
    public function assignedTo(User ...$hrUsers): static
    {
        return $this->afterCreating(function (RecruitmentCampaign $campaign) use ($hrUsers): void {
            $campaign->hrUsers()->attach(collect($hrUsers)->pluck('id'));
        });
    }
}
