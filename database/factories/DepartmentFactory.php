<?php

namespace Database\Factories;

use App\Domain\Candidates\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Sales', 'Marketing', 'Customer Service', 'Operations', 'Finance',
                'Human Resources', 'Engineering', 'Product', 'Logistics', 'Quality Assurance',
            ]).' '.fake()->unique()->numberBetween(1, 9999),
            'is_active' => true,
        ];
    }
}
