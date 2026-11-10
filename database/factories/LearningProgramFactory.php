<?php

namespace Database\Factories;

use App\Domain\Learning\Enums\ProgramStatus;
use App\Domain\Learning\Models\LearningProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningProgram>
 */
class LearningProgramFactory extends Factory
{
    protected $model = LearningProgram::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Program '.rtrim(fake()->unique()->sentence(3), '.'),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['Onboarding', 'Produk', 'SOP', 'Teknis']),
            'target_position_id' => null,
            'status' => ProgramStatus::Draft,
            'estimated_minutes' => fake()->numberBetween(30, 240),
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProgramStatus::Published,
        ]);
    }
}
