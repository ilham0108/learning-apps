<?php

namespace Database\Factories;

use App\Domain\Exams\Enums\ScoringRule;
use App\Domain\Exams\Models\AttemptQuestion;
use App\Domain\Exams\Models\AttemptQuestionOption;
use App\Domain\QuestionBank\Enums\QuestionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttemptQuestionOption>
 */
class AttemptQuestionOptionFactory extends Factory
{
    protected $model = AttemptQuestionOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_question_id' => AttemptQuestion::factory()->state([
                'type' => QuestionType::MultipleChoice,
                'scoring_rule' => ScoringRule::AllOrNothing,
            ]),
            'body_html' => '<p>'.fake()->sentence(4).'</p>',
            'is_correct' => false,
            'position' => fake()->numberBetween(1, 10),
        ];
    }
}
