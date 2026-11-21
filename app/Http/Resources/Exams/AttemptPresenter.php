<?php

namespace App\Http\Resources\Exams;

use App\Domain\Exams\Models\AttemptAnswer;
use App\Domain\Exams\Models\AttemptCategoryScore;
use App\Domain\Exams\Models\AttemptQuestion;
use App\Domain\Exams\Models\AttemptQuestionOption;
use App\Domain\Exams\Models\ExamAttempt;
use Illuminate\Support\Collection;

/**
 * Membentuk data attempt untuk frontend. Soal dan pilihan hanya memakai token
 * acak per attempt (risiko R5). Data runner tidak pernah memuat kunci jawaban,
 * pembahasan, rubrik, atau jawaban diterima (risiko R4); data review memuatnya
 * hanya jika pemanggil mengizinkan.
 */
class AttemptPresenter
{
    /**
     * Status runner: soal tanpa kunci, jawaban tersimpan, posisi, dan waktu server.
     *
     * @return array<string, mixed>
     */
    public static function runnerState(ExamAttempt $attempt): array
    {
        $questions = self::questions($attempt);
        $answers = self::answers($attempt);

        return [
            'attempt' => self::clock($attempt) + [
                'id' => $attempt->public_id,
                'navigation' => $attempt->navigation->value,
                'current_position' => max(1, $attempt->current_position),
                'furthest_position' => $attempt->furthest_position,
            ],
            'items' => $questions->whereNull('parent_id')->values()->map(fn (AttemptQuestion $question) => [
                ...self::runnerQuestion($question),
                'position' => $question->position,
                'children' => $questions->where('parent_id', $question->id)->values()
                    ->map(fn (AttemptQuestion $child) => self::runnerQuestion($child))->all(),
            ])->all(),
            'answers' => (object) $answers
                ->mapWithKeys(fn (AttemptAnswer $answer) => [$answer->question->token => [
                    'selected' => $answer->selectedOptions->pluck('token')->values()->all(),
                    'text' => $answer->text_answer,
                    'flagged' => $answer->is_flagged,
                    'revision' => $answer->client_revision,
                ]])
                ->all(),
        ];
    }

    /**
     * @return array{status: string, deadline_at: string, server_now: string}
     */
    public static function clock(ExamAttempt $attempt): array
    {
        return [
            'status' => $attempt->status->value,
            'deadline_at' => $attempt->deadline_at->toIso8601String(),
            'server_now' => now()->format('Y-m-d\TH:i:s.vP'),
        ];
    }

    /**
     * Ringkasan nilai attempt. Nilai hanya disertakan jika `$withScore`.
     *
     * @return array<string, mixed>
     */
    public static function summary(ExamAttempt $attempt, bool $withScore): array
    {
        return [
            'id' => $attempt->public_id,
            'attempt_number' => $attempt->attempt_number,
            'status' => $attempt->status->value,
            'grading_status' => $attempt->grading_status?->value,
            'started_at' => $attempt->started_at->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'score_visible' => $withScore,
            'score_percent' => $withScore ? $attempt->score_percent : null,
            'earned_points' => $withScore ? $attempt->earned_points : null,
            'max_points' => $withScore ? $attempt->max_points : null,
            'result' => $withScore ? $attempt->result?->value : null,
            'passing_grade' => $attempt->passing_grade,
        ];
    }

    /**
     * @return array<int, array{name: string, earned_points: string, max_points: string}>
     */
    public static function categoryScores(ExamAttempt $attempt): array
    {
        return $attempt->categoryScores()->orderBy('category_name')->get()
            ->map(fn (AttemptCategoryScore $score) => [
                'name' => $score->category_name,
                'earned_points' => $score->earned_points,
                'max_points' => $score->max_points,
            ])->all();
    }

    /**
     * Soal beserta jawaban dan poin. `$withKey` menyertakan kunci dan pembahasan;
     * `$forGrader` menambahkan rubrik, jawaban diterima, dan catatan penilai.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function review(ExamAttempt $attempt, bool $withKey, bool $forGrader = false): array
    {
        $questions = self::questions($attempt);
        $answers = self::answers($attempt)->keyBy('attempt_question_id');

        $present = function (AttemptQuestion $question) use ($answers, $withKey, $forGrader): array {
            /** @var AttemptAnswer|null $answer */
            $answer = $answers->get($question->id);
            $selected = $answer?->selectedOptions->pluck('token')->all() ?? [];

            return [
                'token' => $question->token,
                'type' => $question->type->value,
                'category' => $question->category_name,
                'body_html' => $question->body_html,
                'points' => $question->points,
                'awarded_points' => $answer?->awarded_points,
                'grading_status' => $answer?->grading_status?->value,
                'text' => $answer?->text_answer,
                'flagged' => $answer?->is_flagged ?? false,
                'options' => $question->options->map(fn (AttemptQuestionOption $option) => [
                    'token' => $option->token,
                    'body' => $option->body_html,
                    'selected' => in_array($option->token, $selected, true),
                    'is_correct' => $withKey ? $option->is_correct : null,
                ])->all(),
                'explanation_html' => $withKey ? $question->explanation_html : null,
                ...($forGrader ? [
                    'rubric' => $question->rubric,
                    'accepted_answers' => $question->accepted_answers ?? [],
                    'grader_note' => $answer?->grader_note,
                    'graded_by' => $answer?->grader?->name,
                    'graded_at' => $answer?->graded_at?->toIso8601String(),
                ] : []),
            ];
        };

        return $questions->whereNull('parent_id')->values()->map(fn (AttemptQuestion $question) => [
            ...$present($question),
            'position' => $question->position,
            'children' => $questions->where('parent_id', $question->id)->values()->map($present)->all(),
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerQuestion(AttemptQuestion $question): array
    {
        return [
            'token' => $question->token,
            'type' => $question->type->value,
            'body_html' => $question->body_html,
            'points' => $question->points,
            'options' => $question->options->map(fn (AttemptQuestionOption $option) => [
                'token' => $option->token,
                'body' => $option->body_html,
            ])->all(),
        ];
    }

    /**
     * @return Collection<int, AttemptQuestion>
     */
    private static function questions(ExamAttempt $attempt): Collection
    {
        return $attempt->questions()->with('options')->orderBy('parent_id')->orderBy('position')->get();
    }

    /**
     * @return Collection<int, AttemptAnswer>
     */
    private static function answers(ExamAttempt $attempt): Collection
    {
        return $attempt->answers()->with(['question', 'selectedOptions', 'grader'])->get();
    }
}
