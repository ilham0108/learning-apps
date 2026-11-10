<?php

namespace App\Domain\Exams\Enums;

enum GradingStatus: string
{
    case PendingManual = 'pending_manual';
    case Graded = 'graded';
}
