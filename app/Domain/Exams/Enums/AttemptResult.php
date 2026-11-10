<?php

namespace App\Domain\Exams\Enums;

enum AttemptResult: string
{
    case Passed = 'passed';
    case Failed = 'failed';
}
