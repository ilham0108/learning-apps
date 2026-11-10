<?php

namespace App\Domain\Access\Support;

use App\Domain\Shared\Models\SystemSetting;
use App\Models\User;

class ConsentPolicy
{
    public static function currentVersion(): string
    {
        return (string) SystemSetting::valueOf(
            SystemSetting::CONSENT_POLICY_VERSION,
            config('recruitment.defaults.consent_policy_version'),
        );
    }

    /**
     * Kandidat wajib menyetujui ulang jika versi kebijakan berubah.
     */
    public static function needsConsent(User $user): bool
    {
        if (! $user->isCandidate()) {
            return false;
        }

        return $user->candidateProfile?->consent_policy_version !== self::currentVersion();
    }
}
