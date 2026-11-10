<?php

namespace App\Domain\Access\Actions;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Kandidat membuat password dari tautan aktivasi. Semua lamaran yang masih
 * "invited" otomatis pindah ke "registered" (perubahan oleh kandidat sendiri).
 */
class ActivateCandidateAccount
{
    public function __invoke(User $candidate, string $password): void
    {
        DB::transaction(function () use ($candidate, $password): void {
            /** @var CandidateProfile $profile */
            $profile = CandidateProfile::query()->whereBelongsTo($candidate)->lockForUpdate()->firstOrFail();

            $candidate->forceFill([
                'password' => $password,
                'email_verified_at' => now(),
            ])->save();

            $profile->forceFill(['activated_at' => now()])->save();

            $profile->applications()
                ->where('stage', RecruitmentStage::Invited)
                ->get()
                ->each(function ($application): void {
                    $application->update([
                        'stage' => RecruitmentStage::Registered,
                        'stage_changed_at' => now(),
                    ]);

                    $application->stageHistories()->create([
                        'from_stage' => RecruitmentStage::Invited,
                        'to_stage' => RecruitmentStage::Registered,
                        'changed_by' => null,
                    ]);
                });

            CreateActivationLink::broker()->deleteToken($candidate);
        });
    }
}
