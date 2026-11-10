<?php

namespace Database\Factories;

use App\Domain\QuestionBank\Enums\Difficulty;
use App\Domain\QuestionBank\Enums\QuestionStatus;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\QuestionBank\Models\QuestionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Setiap jenis soal otomatis dilengkapi pilihan, jawaban diterima, atau sub-soal
 * yang sesuai, sehingga soal dari factory selalu valid untuk dipakai di ujian.
 *
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_category_id' => QuestionCategory::factory(),
            'topic' => rtrim(fake()->sentence(2), '.'),
            'difficulty' => fake()->randomElement(Difficulty::cases()),
            'type' => QuestionType::MultipleChoice,
            'body_html' => '<p>'.rtrim(fake()->sentence(10), '.').'?</p>',
            'explanation_html' => '<p>'.fake()->sentence().'</p>',
            'default_points' => 10,
            'status' => QuestionStatus::Active,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Question $question): void {
            match ($question->type) {
                QuestionType::MultipleChoice => $this->createOptions($question, correct: [0], count: 4),
                QuestionType::MultipleSelect => $this->createOptions($question, correct: [0, 2], count: 4),
                QuestionType::TrueFalse => $this->createTrueFalseOptions($question),
                QuestionType::ShortAnswer => $this->createAcceptedAnswers($question),
                QuestionType::CaseStudy => $this->createCaseStudyChildren($question),
                QuestionType::Essay => null,
            };
        });
    }

    public function ofType(QuestionType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'default_points' => $type->isContainer() ? 0 : $attributes['default_points'],
            'rubric' => $type->isManuallyGraded() ? 'Nilai penuh jika jawaban menjelaskan alasan dengan contoh konkret.' : null,
        ]);
    }

    public function multipleChoice(): static
    {
        return $this->ofType(QuestionType::MultipleChoice);
    }

    public function multipleSelect(): static
    {
        return $this->ofType(QuestionType::MultipleSelect);
    }

    public function trueFalse(): static
    {
        return $this->ofType(QuestionType::TrueFalse);
    }

    public function shortAnswer(): static
    {
        return $this->ofType(QuestionType::ShortAnswer);
    }

    public function essay(): static
    {
        return $this->ofType(QuestionType::Essay);
    }

    public function caseStudy(): static
    {
        return $this->ofType(QuestionType::CaseStudy);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuestionStatus::Archived,
        ]);
    }

    /**
     * @param  list<int>  $correct
     */
    private function createOptions(Question $question, array $correct, int $count): void
    {
        if ($question->options()->exists()) {
            return;
        }

        for ($position = 0; $position < $count; $position++) {
            $question->options()->create([
                'body_html' => '<p>'.fake()->sentence(4).'</p>',
                'is_correct' => in_array($position, $correct, true),
                'position' => $position + 1,
            ]);
        }
    }

    private function createTrueFalseOptions(Question $question): void
    {
        if ($question->options()->exists()) {
            return;
        }

        $question->options()->createMany([
            ['body_html' => 'Benar', 'is_correct' => true, 'position' => 1],
            ['body_html' => 'Salah', 'is_correct' => false, 'position' => 2],
        ]);
    }

    private function createAcceptedAnswers(Question $question): void
    {
        if ($question->acceptedAnswers()->exists()) {
            return;
        }

        $question->acceptedAnswers()->create(['answer' => fake()->word()]);
    }

    private function createCaseStudyChildren(Question $question): void
    {
        if ($question->children()->exists()) {
            return;
        }

        $attributes = [
            'question_category_id' => $question->question_category_id,
            'parent_id' => $question->id,
            'difficulty' => $question->difficulty,
        ];

        self::new()->multipleChoice()->create([...$attributes, 'position' => 1]);
        self::new()->essay()->create([...$attributes, 'position' => 2]);
    }
}
