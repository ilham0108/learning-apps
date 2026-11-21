<?php

namespace App\Domain\Exams\Grading;

use App\Domain\Exams\Enums\AnswerGradingStatus;
use App\Domain\Exams\Enums\AttemptResult;
use App\Domain\Exams\Enums\GradingStatus;
use App\Domain\Exams\Enums\ScorePolicy;
use App\Domain\Exams\Models\AttemptAnswer;
use App\Domain\Exams\Models\AttemptQuestion;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use Illuminate\Support\Collection;

/**
 * Satu-satunya tempat penghitungan nilai (risiko R15). Nilai disimpan di attempt
 * saat dihitung; laporan dan halaman membaca nilai tersimpan.
 *
 * Nilai = poin diperoleh ÷ poin maksimal × 100, dengan bobot dari snapshot.
 * Selama masih ada jawaban yang menunggu penilaian manual, nilai bersifat
 * sementara dan `result` tetap kosong.
 */
class ScoreCalculator
{
    public function __construct(private readonly AnswerGrader $grader) {}

    /**
     * Menilai otomatis semua soal saat attempt dikumpulkan. Soal yang tidak
     * dijawab tetap mendapat baris jawaban (0 poin) agar riwayat lengkap.
     */
    public function gradeSubmission(ExamAttempt $attempt): void
    {
        $answers = $attempt->answers()->with('selectedOptions')->get()->keyBy('attempt_question_id');

        foreach ($this->gradableQuestions($attempt) as $question) {
            /** @var AttemptAnswer|null $answer */
            $answer = $answers->get($question->id);
            $grade = $this->grader->grade($question, $answer);

            $values = [
                'auto_points' => $grade['points'],
                'awarded_points' => $grade['points'],
                'grading_status' => $grade['status'],
            ];

            if ($answer === null) {
                $attempt->answers()->create(['attempt_question_id' => $question->id, ...$values]);
            } else {
                $answer->update($values);
            }
        }

        $this->recalculate($attempt);
    }

    /**
     * Menghitung ulang nilai total, nilai per kategori, status penilaian, dan hasil.
     */
    public function recalculate(ExamAttempt $attempt): void
    {
        $questions = $this->gradableQuestions($attempt);
        $answers = $attempt->answers()->get()->keyBy('attempt_question_id');

        $earned = 0.0;
        $pending = false;
        $categories = [];

        foreach ($questions as $question) {
            /** @var AttemptAnswer|null $answer */
            $answer = $answers->get($question->id);
            $points = (float) ($answer?->awarded_points ?? 0);
            $pending = $pending || $answer?->grading_status === AnswerGradingStatus::PendingManual;
            $earned += $points;

            $category = $categories[$question->category_name] ??= [
                'question_category_id' => $question->question_category_id,
                'earned' => 0.0,
                'max' => 0.0,
            ];
            $category['earned'] += $points;
            $category['max'] += (float) $question->points;
            $categories[$question->category_name] = $category;
        }

        $max = (float) $attempt->max_points;
        $percent = $max > 0 ? $earned / $max * 100 : 0.0;
        $graded = ! $pending;

        $attempt->forceFill([
            'earned_points' => $this->decimal($earned),
            'score_percent' => $this->decimal($percent),
            'grading_status' => $graded ? GradingStatus::Graded : GradingStatus::PendingManual,
            'result' => $graded
                ? ($percent >= (float) $attempt->passing_grade ? AttemptResult::Passed : AttemptResult::Failed)
                : null,
            'finalized_at' => $graded ? ($attempt->finalized_at ?? now()) : null,
        ])->save();

        $attempt->categoryScores()->delete();

        foreach ($categories as $name => $category) {
            $attempt->categoryScores()->create([
                'question_category_id' => $category['question_category_id'],
                'category_name' => $name,
                'earned_points' => $this->decimal($category['earned']),
                'max_points' => $this->decimal($category['max']),
            ]);
        }

        $this->refreshEffectiveAttempt($attempt->assignment);
    }

    /**
     * Attempt yang menentukan nilai penugasan menurut kebijakan percobaan:
     * nilai tertinggi dari attempt yang sudah final, atau attempt terakhir yang selesai.
     */
    public function refreshEffectiveAttempt(ExamAssignment $assignment): void
    {
        $finished = ExamAttempt::query()
            ->where('exam_assignment_id', $assignment->id)
            ->where('status', '!=', 'in_progress')
            ->get();

        $policy = $finished->first()->score_policy ?? $assignment->exam->score_policy;

        $effective = $policy === ScorePolicy::Latest
            ? $finished->sortByDesc('attempt_number')->first()
            : $finished
                ->filter(fn (ExamAttempt $attempt) => $attempt->isGraded())
                ->sortBy([
                    fn (ExamAttempt $a, ExamAttempt $b) => (float) $b->score_percent <=> (float) $a->score_percent,
                    fn (ExamAttempt $a, ExamAttempt $b) => $a->attempt_number <=> $b->attempt_number,
                ])
                ->first();

        $assignment->forceFill(['effective_attempt_id' => $effective?->id])->save();
    }

    /**
     * Soal yang memiliki poin: semua soal kecuali induk studi kasus (aturan B11).
     *
     * @return Collection<int, AttemptQuestion>
     */
    private function gradableQuestions(ExamAttempt $attempt): Collection
    {
        return AttemptQuestion::query()
            ->where('exam_attempt_id', $attempt->id)
            ->where('type', '!=', 'case_study')
            ->with('options')
            ->get();
    }

    private function decimal(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
