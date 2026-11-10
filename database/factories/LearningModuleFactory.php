<?php

namespace Database\Factories;

use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Models\LearningProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningModule>
 */
class LearningModuleFactory extends Factory
{
    protected $model = LearningModule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_program_id' => LearningProgram::factory(),
            'title' => 'Modul '.rtrim(fake()->sentence(3), '.'),
            'description' => fake()->sentence(),
            'position' => fake()->numberBetween(1, 20),
        ];
    }
}
