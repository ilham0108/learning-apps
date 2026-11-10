<?php

namespace App\Domain\Candidates\Enums;

enum RecruitmentStage: string
{
    case Invited = 'invited';
    case Registered = 'registered';
    case UnderReview = 'under_review';
    case Shortlisted = 'shortlisted';
    case Rejected = 'rejected';
    case Hired = 'hired';
    case Withdrawn = 'withdrawn';

    /**
     * Transisi yang boleh dilakukan HR (docs/tahap-1.md bagian 10.1).
     * Perpindahan invited → registered terjadi otomatis saat aktivasi akun.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Invited => [self::Registered, self::Withdrawn],
            self::Registered => [self::UnderReview, self::Withdrawn],
            self::UnderReview => [self::Shortlisted, self::Rejected, self::Withdrawn],
            self::Shortlisted => [self::Hired, self::Rejected, self::Withdrawn],
            self::Rejected, self::Hired, self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Koreksi yang hanya boleh dilakukan Super Admin, di luar alur normal.
     */
    public function canBeCorrectedTo(self $target): bool
    {
        return $this === self::Rejected && $target === self::UnderReview;
    }

    /**
     * Perubahan manual oleh HR/Super Admin. Aktivasi (invited → registered) tidak termasuk
     * karena dilakukan kandidat sendiri lewat tautan aktivasi.
     */
    public function canBeChangedManuallyTo(self $target, bool $bySuperAdmin): bool
    {
        if ($this === self::Invited && $target === self::Registered) {
            return false;
        }

        return $this->canTransitionTo($target) || ($bySuperAdmin && $this->canBeCorrectedTo($target));
    }

    public function requiresReason(self $target): bool
    {
        return in_array($target, [self::Rejected, self::Withdrawn], true)
            || $this->canBeCorrectedTo($target);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
