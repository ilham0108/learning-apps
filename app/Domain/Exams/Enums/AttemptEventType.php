<?php

namespace App\Domain\Exams\Enums;

enum AttemptEventType: string
{
    case Started = 'started';
    case Resumed = 'resumed';
    case TabHidden = 'tab_hidden';
    case TabVisible = 'tab_visible';
    case SessionReplaced = 'session_replaced';
    case Submitted = 'submitted';
    case AutoSubmitted = 'auto_submitted';
    case Expired = 'expired';
}
