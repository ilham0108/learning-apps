<?php

namespace Database\Factories;

use App\Domain\Exams\Enums\ScoringRule;
use App\Domain\Exams\Models\AttemptQuestion;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\QuestionBank\Enums\QuestionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttemptQuestion>
 */
class AttemptQuestionFactory extends Factory
{
    protected $model = AttemptQuestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_attempt_id' => ExamAttempt::factory(),
            'category_name' => ucfirst(fake()->word()),
            'position' => fake()->numberBetween(1, 50),
            'type' => QuestionType::Essay,
            'body_html' => '<p>'.fake()->sentence().'</p>',
            'points' => 10,
            'scoring_rule' => ScoringRule::Manual,
        ];
    }
}
