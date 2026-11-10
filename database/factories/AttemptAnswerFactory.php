<?php

namespace Database\Factories;

use App\Domain\Exams\Models\AttemptAnswer;
use App\Domain\Exams\Models\AttemptQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttemptAnswer>
 */
class AttemptAnswerFactory extends Factory
{
    protected $model = AttemptAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_question_id' => AttemptQuestion::factory(),
            'exam_attempt_id' => fn (array $attributes) => AttemptQuestion::query()
                ->whereKey($attributes['attempt_question_id'])
                ->value('exam_attempt_id'),
            'text_answer' => fake()->paragraph(),
            'is_flagged' => false,
            'client_revision' => 1,
            'saved_at' => now(),
        ];
    }
}
