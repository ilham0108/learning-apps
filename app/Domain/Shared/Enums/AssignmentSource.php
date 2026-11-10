<?php

namespace App\Domain\Shared\Enums;

enum AssignmentSource: string
{
    case Individual = 'individual';
    case Campaign = 'campaign';
    case Position = 'position';
    case Selection = 'selection';
}
