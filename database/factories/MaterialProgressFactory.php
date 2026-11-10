<?php

namespace Database\Factories;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\MaterialProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialProgress>
 */
class MaterialProgressFactory extends Factory
{
    protected $model = MaterialProgress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'learning_material_id' => LearningMaterial::factory(),
            'first_opened_at' => now()->subHour(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }
}
