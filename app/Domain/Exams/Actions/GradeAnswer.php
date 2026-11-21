<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Exams\Enums\AnswerGradingStatus;
use App\Domain\Exams\Grading\ScoreCalculator;
use App\Domain\Exams\Models\AttemptAnswer;
use App\Domain\Exams\Models\AttemptQuestion;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penilaian manual esai dan isian singkat oleh HR (rules Bagian 10). Penilai dan
 * waktunya disimpan di jawaban. Mengubah nilai attempt yang sudah final wajib
 * disertai alasan dan dicatat di audit log.
 */
class GradeAnswer
{
    public function __construct(
        private readonly ScoreCalculator $calculator,
        private readonly ActivityLogger $logger,
    ) {}

    public function __invoke(ExamAttempt $attempt, string $questionToken, string $points, ?string $note, ?string $reason, User $grader): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $questionToken, $points, $note, $reason, $grader) {
            /** @var ExamAttempt $locked */
            $locked = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->isInProgress()) {
                throw ValidationException::withMessages(['points' => __('Attempt yang masih dikerjakan belum dapat dinilai.')]);
            }

            /** @var AttemptQuestion $question */
            $question = AttemptQuestion::query()
                ->where('exam_attempt_id', $locked->id)
                ->where('token', $questionToken)
                ->whereIn('type', [QuestionType::Essay->value, QuestionType::ShortAnswer->value])
                ->firstOrFail();

            if ((float) $points < 0 || (float) $points > (float) $question->points) {
                throw ValidationException::withMessages(['points' => __('Poin harus antara 0 dan :max.', ['max' => $question->points])]);
            }

            $wasFinal = $locked->isGraded();
            $reason = $reason === null ? null : trim($reason);

            if ($wasFinal && ($reason === null || $reason === '')) {
                throw ValidationException::withMessages(['reason' => __('Alasan wajib diisi untuk mengubah nilai yang sudah final.')]);
            }

            /** @var AttemptAnswer $answer */
            $answer = AttemptAnswer::query()->firstOrCreate([
                'exam_attempt_id' => $locked->id,
                'attempt_question_id' => $question->id,
            ]);
            $previousPoints = $answer->awarded_points;
            $previousScore = $locked->score_percent;

            $answer->update([
                'manual_points' => $points,
                'awarded_points' => $points,
                'grading_status' => AnswerGradingStatus::ManuallyGraded,
                'graded_by' => $grader->id,
                'graded_at' => now(),
                'grader_note' => $note === null || trim($note) === '' ? null : trim($note),
            ]);

            $this->calculator->recalculate($locked);
            $locked->refresh();

            if ($wasFinal) {
                $this->logger->log('score.changed', $locked, [
                    'question' => $question->token,
                    'points' => ['from' => $previousPoints, 'to' => $answer->awarded_points],
                    'score_percent' => ['from' => $previousScore, 'to' => $locked->score_percent],
                ], reason: $reason, actor: $grader);
            }

            return $locked;
        });
    }
}
