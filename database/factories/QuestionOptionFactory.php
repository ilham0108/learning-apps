<?php

namespace Database\Factories;

use App\Domain\QuestionBank\Models\Question;
use App\Domain\QuestionBank\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory()->essay(),
            'body_html' => '<p>'.fake()->sentence(4).'</p>',
            'is_correct' => false,
            'position' => fake()->numberBetween(1, 10),
        ];
    }

    public function correct(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
        ]);
    }
}
