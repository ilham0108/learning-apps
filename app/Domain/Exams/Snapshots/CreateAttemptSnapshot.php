<?php

namespace App\Domain\Exams\Snapshots;

use App\Domain\Exams\Enums\ScoringRule;
use App\Domain\Exams\Models\AttemptQuestion;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Exams\Models\ExamQuestion;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\QuestionBank\Models\QuestionAcceptedAnswer;
use App\Domain\QuestionBank\Models\QuestionOption;
use Illuminate\Support\Collection;

/**
 * Menyalin soal, pilihan, bobot, dan kunci jawaban ke attempt beserta urutan
 * acaknya (rules Bagian 9 langkah 8). Setelah ini attempt tidak lagi bergantung
 * pada bank soal, sehingga mengedit soal tidak mengubah attempt lama.
 */
class CreateAttemptSnapshot
{
    /**
     * @return string Total poin maksimal attempt.
     */
    public function __invoke(Exam $exam, ExamAttempt $attempt): string
    {
        $examQuestions = $exam->examQuestions()
            ->with([
                'question.category',
                'question.options',
                'question.acceptedAnswers',
                'question.children.category',
                'question.children.options',
                'question.children.acceptedAnswers',
            ])
            ->get();

        if ($exam->shuffle_questions) {
            $examQuestions = $examQuestions->shuffle();
        }

        $total = 0.0;

        foreach ($examQuestions->values() as $index => $examQuestion) {
            $total += $this->copy($exam, $attempt, $examQuestion, $index + 1);
        }

        return number_format($total, 2, '.', '');
    }

    private function copy(Exam $exam, ExamAttempt $attempt, ExamQuestion $examQuestion, int $position): float
    {
        $question = $examQuestion->question;

        if (! $question->type->isContainer()) {
            $this->store($exam, $attempt, $question, $position, $examQuestion->points, null);

            return (float) $examQuestion->points;
        }

        // Studi kasus: induk berisi teks kasus (0 poin), poin ada di sub-soal (aturan B11).
        $parent = $this->store($exam, $attempt, $question, $position, '0.00', null);
        $total = 0.0;

        foreach ($question->children->values() as $index => $child) {
            $this->store($exam, $attempt, $child, $index + 1, $child->default_points, $parent->id, $question);
            $total += (float) $child->default_points;
        }

        return $total;
    }

    private function store(
        Exam $exam,
        ExamAttempt $attempt,
        Question $question,
        int $position,
        string $points,
        ?int $parentId,
        ?Question $parent = null,
    ): AttemptQuestion {
        $category = $question->category ?? $parent?->category;

        $snapshot = $attempt->questions()->create([
            'parent_id' => $parentId,
            'source_question_id' => $question->id,
            'question_category_id' => $category?->id,
            'category_name' => $category->name ?? __('Tanpa kategori'),
            'position' => $position,
            'type' => $question->type,
            'body_html' => $question->body_html,
            'explanation_html' => $question->explanation_html,
            'rubric' => $question->rubric,
            'points' => $points,
            'scoring_rule' => $this->scoringRule($exam, $question->type),
            'accepted_answers' => $question->type === QuestionType::ShortAnswer
                ? $question->acceptedAnswers->map(fn (QuestionAcceptedAnswer $answer) => $answer->normalized)->values()->all()
                : null,
        ]);

        $this->copyOptions($exam, $snapshot, $question);

        return $snapshot;
    }

    private function copyOptions(Exam $exam, AttemptQuestion $snapshot, Question $question): void
    {
        if (! $question->type->hasOptions()) {
            return;
        }

        /** @var Collection<int, QuestionOption> $options */
        $options = $question->options;

        // Urutan Benar/Salah tidak diacak agar tidak membingungkan.
        if ($exam->shuffle_options && $question->type !== QuestionType::TrueFalse) {
            $options = $options->shuffle();
        }

        foreach ($options->values() as $index => $option) {
            $snapshot->options()->create([
                'body_html' => $option->body_html,
                'is_correct' => $option->is_correct,
                'position' => $index + 1,
            ]);
        }
    }

    private function scoringRule(Exam $exam, QuestionType $type): ScoringRule
    {
        return match ($type) {
            QuestionType::MultipleSelect => ScoringRule::from($exam->multi_select_scoring->value),
            QuestionType::ShortAnswer => ScoringRule::ExactMatch,
            QuestionType::Essay => ScoringRule::Manual,
            default => ScoringRule::AllOrNothing,
        };
    }
}
