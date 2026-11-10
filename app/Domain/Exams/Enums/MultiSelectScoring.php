<?php

namespace App\Domain\Exams\Enums;

enum MultiSelectScoring: string
{
    case AllOrNothing = 'all_or_nothing';
    case Partial = 'partial';
}
