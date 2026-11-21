<?php

namespace App\Domain\Candidates\Actions;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Exams\Actions\SubmitAttempt;
use App\Domain\Exams\Enums\AttemptStatus;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Menonaktifkan atau mengaktifkan kembali akun kandidat (aturan B3). Data tetap ada;
 * kandidat nonaktif tidak bisa login dan sesinya diakhiri oleh middleware.
 * Attempt ujian yang sedang berjalan langsung dikumpulkan otomatis.
 */
class SetCandidateActive
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly SubmitAttempt $submit,
    ) {}

    public function __invoke(CandidateProfile $profile, bool $active, ?string $reason, User $actor): void
    {
        DB::transaction(function () use ($profile, $active, $reason, $actor): void {
            $profile->user->forceFill(['is_active' => $active])->save();

            if (! $active) {
                ExamAttempt::query()
                    ->where('status', AttemptStatus::InProgress)
                    ->whereHas('assignment.application', fn ($query) => $query->where('candidate_profile_id', $profile->id))
                    ->get()
                    ->each(fn (ExamAttempt $attempt) => ($this->submit)($attempt, auto: true));
            }

            $this->logger->log(
                $active ? 'candidate.activated' : 'candidate.deactivated',
                $profile,
                reason: $reason,
                actor: $actor,
            );
        });
    }
}
