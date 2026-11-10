<?php

namespace Database\Factories;

use App\Domain\Access\Enums\DataDeletionStatus;
use App\Domain\Access\Models\DataDeletionRequest;
use App\Domain\Candidates\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataDeletionRequest>
 */
class DataDeletionRequestFactory extends Factory
{
    protected $model = DataDeletionRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'reason' => fake()->sentence(),
            'status' => DataDeletionStatus::Pending,
        ];
    }
}
