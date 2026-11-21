<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Exams\Enums\AttemptEventType;
use App\Domain\Exams\Enums\AttemptStatus;
use App\Domain\Exams\Grading\ScoreCalculator;
use App\Domain\Exams\Models\ExamAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Mengakhiri attempt dan menilainya. Idempotent: baris attempt dikunci
 * (`SELECT ... FOR UPDATE`) dan hanya diproses jika masih `in_progress`;
 * pemanggilan kedua mengembalikan attempt yang sama tanpa menilai ulang (risiko R2).
 *
 * Auto-submit (deadline tercapai) menghasilkan `auto_submitted`, atau `expired`
 * jika kandidat tidak menyimpan jawaban apa pun.
 */
class SubmitAttempt
{
    public function __construct(
        private readonly ScoreCalculator $calculator,
    ) {}

    public function __invoke(ExamAttempt $attempt, bool $auto = false): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $auto) {
            /** @var ExamAttempt $locked */
            $locked = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (! $locked->isInProgress()) {
                return $locked;
            }

            $now = now();
            // Kandidat yang menekan "Kumpulkan" setelah deadline diperlakukan sama dengan auto-submit.
            $auto = $auto || $now->greaterThanOrEqualTo($locked->deadline_at);
            $hasAnswers = $locked->answers()
                ->where(fn ($query) => $query->whereNotNull('text_answer')->where('text_answer', '!=', '')
                    ->orWhereHas('selectedOptions'))
                ->exists();

            $status = match (true) {
                ! $auto => AttemptStatus::Submitted,
                $hasAnswers => AttemptStatus::AutoSubmitted,
                default => AttemptStatus::Expired,
            };

            $locked->forceFill([
                'status' => $status,
                'submitted_at' => $auto ? $locked->deadline_at->min($now) : $now,
            ])->save();

            $this->calculator->gradeSubmission($locked);

            $locked->events()->create([
                'type' => match ($status) {
                    AttemptStatus::Submitted => AttemptEventType::Submitted,
                    AttemptStatus::AutoSubmitted => AttemptEventType::AutoSubmitted,
                    default => AttemptEventType::Expired,
                },
                // Auto-submit oleh scheduler tidak memiliki IP kandidat.
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
                'occurred_at' => $now,
            ]);

            return $locked->refresh();
        });
    }
}
