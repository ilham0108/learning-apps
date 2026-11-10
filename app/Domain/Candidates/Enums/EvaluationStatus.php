<?php

namespace App\Domain\Candidates\Enums;

enum EvaluationStatus: string
{
    case Draft = 'draft';
    case Final = 'final';
}
