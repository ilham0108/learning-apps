<?php

namespace App\Domain\Exams\Grading;

use App\Domain\Exams\Enums\AnswerGradingStatus;
use App\Domain\Exams\Enums\ScoringRule;
use App\Domain\Exams\Models\AttemptAnswer;
use App\Domain\Exams\Models\AttemptQuestion;
use App\Domain\Exams\Models\AttemptQuestionOption;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\QuestionAcceptedAnswer;

/**
 * Menilai satu jawaban otomatis berdasarkan snapshot soal. Jawaban yang butuh
 * penilaian manusia (esai, isian singkat yang tidak cocok) dikembalikan
 * sebagai `pending_manual` tanpa poin.
 */
class AnswerGrader
{
    /**
     * @return array{status: AnswerGradingStatus, points: string|null}
     */
    public function grade(AttemptQuestion $question, ?AttemptAnswer $answer): array
    {
        $max = (float) $question->points;

        return match ($question->type) {
            QuestionType::MultipleChoice, QuestionType::TrueFalse => $this->auto($this->singleChoice($question, $answer) ? $max : 0.0),
            QuestionType::MultipleSelect => $this->auto($this->multipleSelect($question, $answer) * $max),
            QuestionType::ShortAnswer => $this->shortAnswer($question, $answer, $max),
            QuestionType::Essay => $this->isBlank($answer) ? $this->auto(0.0) : $this->pending(),
            QuestionType::CaseStudy => $this->auto(0.0),
        };
    }

    private function singleChoice(AttemptQuestion $question, ?AttemptAnswer $answer): bool
    {
        $selected = $this->selectedIds($answer);

        return count($selected) === 1 && in_array($selected[0], $this->correctIds($question), true);
    }

    /**
     * Fraksi poin 0–1. Parsial: max(0, (benar dipilih − salah dipilih) / jumlah benar) (aturan B10).
     */
    private function multipleSelect(AttemptQuestion $question, ?AttemptAnswer $answer): float
    {
        $selected = $this->selectedIds($answer);
        $correct = $this->correctIds($question);

        if ($correct === []) {
            return 0.0;
        }

        $right = count(array_intersect($selected, $correct));
        $wrong = count(array_diff($selected, $correct));

        if ($question->scoring_rule === ScoringRule::Partial) {
            return max(0.0, ($right - $wrong) / count($correct));
        }

        return $wrong === 0 && $right === count($correct) ? 1.0 : 0.0;
    }

    /**
     * Cocok dengan salah satu jawaban diterima → poin penuh. Tidak cocok → dinilai HR (aturan B9).
     *
     * @return array{status: AnswerGradingStatus, points: string|null}
     */
    private function shortAnswer(AttemptQuestion $question, ?AttemptAnswer $answer, float $max): array
    {
        if ($this->isBlank($answer)) {
            return $this->auto(0.0);
        }

        $normalized = QuestionAcceptedAnswer::normalize((string) $answer->text_answer);

        return in_array($normalized, $question->accepted_answers ?? [], true)
            ? $this->auto($max)
            : $this->pending();
    }

    private function isBlank(?AttemptAnswer $answer): bool
    {
        return $answer === null || trim((string) $answer->text_answer) === '';
    }

    /**
     * @return array<int, int>
     */
    private function selectedIds(?AttemptAnswer $answer): array
    {
        if ($answer === null) {
            return [];
        }

        return $answer->selectedOptions->map(fn (AttemptQuestionOption $option) => $option->id)->values()->all();
    }

    /**
     * @return array<int, int>
     */
    private function correctIds(AttemptQuestion $question): array
    {
        return $question->options
            ->filter(fn (AttemptQuestionOption $option) => $option->is_correct)
            ->map(fn (AttemptQuestionOption $option) => $option->id)
            ->values()
            ->all();
    }

    /**
     * @return array{status: AnswerGradingStatus, points: string}
     */
    private function auto(float $points): array
    {
        return ['status' => AnswerGradingStatus::AutoGraded, 'points' => number_format($points, 2, '.', '')];
    }

    /**
     * @return array{status: AnswerGradingStatus, points: null}
     */
    private function pending(): array
    {
        return ['status' => AnswerGradingStatus::PendingManual, 'points' => null];
    }
}
