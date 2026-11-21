<?php

namespace Database\Factories;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecruitmentApplication>
 */
class RecruitmentApplicationFactory extends Factory
{
    protected $model = RecruitmentApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'recruitment_campaign_id' => RecruitmentCampaign::factory(),
            'stage' => RecruitmentStage::Invited,
            'stage_changed_at' => now(),
            'applied_at' => now(),
        ];
    }

    public function stage(RecruitmentStage $stage): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => $stage,
        ]);
    }

    /**
     * Lamaran di tahap proses ke-`$position` (1 = tahap pertama) rekrutmennya.
     */
    public function inProcess(int $position = 1): static
    {
        return $this->stage(RecruitmentStage::InProcess)->afterCreating(function (RecruitmentApplication $application) use ($position): void {
            $stage = $application->campaign->stages()->where('position', $position)->firstOrFail();
            $application->forceFill(['campaign_stage_id' => $stage->id])->save();
        });
    }
}
