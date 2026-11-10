<?php

namespace Database\Factories;

use App\Domain\Candidates\Models\Department;
use App\Domain\Candidates\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->unique()->jobTitle(),
            'is_active' => true,
        ];
    }
}
