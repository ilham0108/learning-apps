<?php

namespace App\Domain\Exams\Enums;

enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case AutoSubmitted = 'auto_submitted';
    case Expired = 'expired';

    public function isFinished(): bool
    {
        return $this !== self::InProgress;
    }
}
