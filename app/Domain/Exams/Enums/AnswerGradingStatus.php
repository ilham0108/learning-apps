<?php

namespace App\Domain\Exams\Enums;

enum AnswerGradingStatus: string
{
    case AutoGraded = 'auto_graded';
    case PendingManual = 'pending_manual';
    case ManuallyGraded = 'manually_graded';
}
