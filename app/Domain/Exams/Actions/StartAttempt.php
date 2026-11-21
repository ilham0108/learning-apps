<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Exams\Enums\AttemptEventType;
use App\Domain\Exams\Enums\AttemptStatus;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Exams\Queries\ExamAvailability;
use App\Domain\Exams\Snapshots\CreateAttemptSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Memulai atau melanjutkan attempt (rules Bagian 9). Seluruh langkah berjalan di
 * satu transaction dengan lock pada baris penugasan, sehingga dua klik "Mulai"
 * bersamaan tidak membuat dua attempt (risiko R1).
 */
class StartAttempt
{
    public function __construct(
        private readonly ExamAvailability $availability,
        private readonly CreateAttemptSnapshot $snapshot,
        private readonly SubmitAttempt $submit,
    ) {}

    public function __invoke(ExamAssignment $assignment, User $candidate): ExamAttempt
    {
        return DB::transaction(function () use ($assignment, $candidate) {
            /** @var ExamAssignment $assignment */
            $assignment = ExamAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if (! $candidate->is_active) {
                throw ValidationException::withMessages(['exam' => __('Akun Anda dinonaktifkan. Hubungi tim HR.')]);
            }

            $active = $assignment->attempts()->where('status', AttemptStatus::InProgress)->first();

            // Attempt yang masih berjalan dilanjutkan; peristiwa "dilanjutkan" dicatat saat runner dibuka.
            if ($active !== null && ! $active->isPastDeadline()) {
                return $active;
            }

            if ($active !== null) {
                ($this->submit)($active, auto: true);
            }

            $check = $this->availability->check($assignment);

            if ($check['state'] !== ExamAvailability::AVAILABLE) {
                throw ValidationException::withMessages(['exam' => ExamAvailability::message($check['state'])]);
            }

            return $this->create($assignment, $check['attempts_used'] + 1);
        });
    }

    private function create(ExamAssignment $assignment, int $number): ExamAttempt
    {
        // Kunci ujian bersamaan dengan pengelolaan soal (ManageExamQuestions) agar
        // soal tidak berubah saat snapshot dibuat; attempt pertama mengunci ujian (B8).
        /** @var Exam $exam */
        $exam = Exam::query()->lockForUpdate()->findOrFail($assignment->exam_id);

        if ($exam->locked_at === null) {
            $exam->forceFill(['locked_at' => now()])->save();
        }

        $startedAt = now();
        $deadline = $startedAt->addMinutes($exam->duration_minutes);
        $closesAt = $assignment->effectiveClosesAt();

        if ($closesAt !== null && $closesAt->lessThan($deadline)) {
            $deadline = $closesAt;
        }

        $attempt = $assignment->attempts()->create([
            'attempt_number' => $number,
            'status' => AttemptStatus::InProgress,
            'started_at' => $startedAt,
            'deadline_at' => $deadline,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'max_points' => 0,
            'passing_grade' => $exam->passing_grade,
            'navigation' => $exam->navigation,
            'score_policy' => $exam->score_policy,
            'result_release' => $exam->result_release,
        ]);

        $attempt->forceFill(['max_points' => ($this->snapshot)($exam, $attempt)])->save();
        $this->event($attempt, AttemptEventType::Started);

        return $attempt;
    }

    private function event(ExamAttempt $attempt, AttemptEventType $type): void
    {
        $attempt->events()->create([
            'type' => $type,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}
