<?php

namespace App\Domain\Candidates\Actions;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Menonaktifkan atau mengaktifkan kembali akun kandidat (aturan B3). Data tetap ada;
 * kandidat nonaktif tidak bisa login dan sesinya diakhiri oleh middleware.
 * Penutupan attempt ujian yang sedang berjalan ditambahkan bersama mesin ujian (Tahap 7).
 */
class SetCandidateActive
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function __invoke(CandidateProfile $profile, bool $active, ?string $reason, User $actor): void
    {
        DB::transaction(function () use ($profile, $active, $reason, $actor): void {
            $profile->user->forceFill(['is_active' => $active])->save();

            $this->logger->log(
                $active ? 'candidate.activated' : 'candidate.deactivated',
                $profile,
                reason: $reason,
                actor: $actor,
            );
        });
    }
}
