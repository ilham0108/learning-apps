<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Support\ConsentPolicy;
use App\Models\User;

/**
 * Mencatat persetujuan pemrosesan data pribadi (UU No. 27/2022) beserta waktu dan versi kebijakan.
 */
class RecordConsent
{
    public function __invoke(User $candidate): void
    {
        $candidate->candidateProfile?->forceFill([
            'consent_given_at' => now(),
            'consent_policy_version' => ConsentPolicy::currentVersion(),
        ])->save();
    }
}
