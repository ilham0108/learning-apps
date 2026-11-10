<?php

namespace Database\Factories;

use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Enums\ExamType;
use App\Domain\Exams\Enums\MultiSelectScoring;
use App\Domain\Exams\Enums\NavigationMode;
use App\Domain\Exams\Enums\ResultRelease;
use App\Domain\Exams\Enums\ScorePolicy;
use App\Domain\Exams\Models\Exam;
use App\Domain\QuestionBank\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Ujian '.rtrim(fake()->unique()->sentence(3), '.'),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(ExamType::cases()),
            'duration_minutes' => 60,
            'passing_grade' => 70,
            'max_attempts' => 1,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(14),
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'navigation' => NavigationMode::Free,
            'score_policy' => ScorePolicy::Highest,
            'multi_select_scoring' => MultiSelectScoring::AllOrNothing,
            'result_release' => ResultRelease::Immediate,
            'show_review' => false,
            'status' => ExamStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExamStatus::Published,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExamStatus::Published,
            'locked_at' => now(),
        ]);
    }

    /**
     * Lampirkan soal ke ujian dengan bobot default masing-masing.
     *
     * @param  iterable<Question>  $questions
     */
    public function withQuestions(iterable $questions): static
    {
        return $this->afterCreating(function (Exam $exam) use ($questions): void {
            $position = 1;

            foreach ($questions as $question) {
                $exam->examQuestions()->create([
                    'question_id' => $question->id,
                    'points' => $question->default_points,
                    'position' => $position++,
                ]);
            }
        });
    }
}
