<?php

namespace App\Domain\Exams\Queries;

use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Exams\Enums\AttemptStatus;
use App\Domain\Exams\Enums\GradingStatus;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Exams\Support\AttemptAccess;
use App\Domain\Exams\Support\ResultVisibility;
use App\Http\Resources\Exams\AttemptPresenter;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Ujian milik kandidat beserta ketersediaan, progres, dan nilai yang boleh
 * dilihat. Dipakai halaman Ujian dan dashboard kandidat.
 */
class CandidateExams
{
    public function __construct(
        private readonly ExamAvailability $availability,
        private readonly AttemptAccess $access,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(User $candidate): array
    {
        return ExamAssignment::query()
            ->whereIn('recruitment_application_id', RecruitmentApplication::query()->select('id')->visibleTo($candidate))
            ->with(['exam', 'application.campaign'])
            ->latest('id')
            ->get()
            ->map(fn (ExamAssignment $assignment) => $this->row($assignment))
            ->all();
    }

    /**
     * Attempt selesai dengan nilai yang sudah boleh dilihat kandidat, terbaru dulu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function publishedResults(User $candidate, int $limit = 5): array
    {
        return ExamAttempt::query()
            ->visibleTo($candidate)
            ->where('status', '!=', AttemptStatus::InProgress)
            ->with('assignment.exam')
            ->latest('submitted_at')
            ->get()
            ->filter(fn (ExamAttempt $attempt) => ResultVisibility::scoreVisible($attempt))
            ->take($limit)
            ->map(fn (ExamAttempt $attempt) => AttemptPresenter::summary($attempt, true) + [
                'exam' => $attempt->assignment->exam->title,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ExamAssignment $assignment): array
    {
        $this->closeExpired($assignment);
        $check = $this->availability->check($assignment);
        $attempts = $assignment->attempts()->get();
        $latest = $attempts->last();

        return [
            'id' => $assignment->public_id,
            'title' => $assignment->exam->title,
            'type' => $assignment->exam->type->value,
            'campaign' => $assignment->application->campaign->name,
            'duration_minutes' => $assignment->exam->duration_minutes,
            'opens_at' => $assignment->effectiveOpensAt()?->toIso8601String(),
            'closes_at' => $assignment->effectiveClosesAt()?->toIso8601String(),
            'availability' => $check['state'],
            'active_attempt' => $check['active_attempt']?->public_id,
            'progress' => $this->progressOf($attempts, $check['active_attempt']),
            'attempts_used' => $check['attempts_used'],
            'max_attempts' => $assignment->effectiveMaxAttempts(),
            'latest' => $latest === null
                ? null
                : AttemptPresenter::summary($latest, ResultVisibility::scoreVisible($latest->setRelation('assignment', $assignment))),
        ];
    }

    /**
     * Cek kedaluwarsa saat akses untuk attempt yang masih tercatat berjalan.
     */
    public function closeExpired(ExamAssignment $assignment): void
    {
        $active = $assignment->attempts()->where('status', AttemptStatus::InProgress)->first();

        if ($active !== null) {
            $this->access->closeIfExpired($active);
        }
    }

    /**
     * Status tampilan: belum dimulai, berlangsung, menunggu penilaian, selesai.
     *
     * @param  Collection<int, ExamAttempt>  $attempts
     */
    private function progressOf(Collection $attempts, ?ExamAttempt $active): string
    {
        return match (true) {
            $active !== null => 'in_progress',
            $attempts->isEmpty() => 'not_started',
            $attempts->last()->grading_status === GradingStatus::PendingManual => 'awaiting_grading',
            default => 'completed',
        };
    }
}
