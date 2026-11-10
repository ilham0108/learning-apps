<?php

namespace App\Domain\Learning\Enums;

enum LearningProgressStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
