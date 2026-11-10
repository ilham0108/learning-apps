<?php

namespace App\Domain\Exams\Enums;

enum ScoringRule: string
{
    case AllOrNothing = 'all_or_nothing';
    case Partial = 'partial';
    case ExactMatch = 'exact_match';
    case Manual = 'manual';
}
