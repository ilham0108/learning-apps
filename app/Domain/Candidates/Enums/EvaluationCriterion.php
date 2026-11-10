<?php

namespace App\Domain\Candidates\Enums;

enum EvaluationCriterion: string
{
    case Exam = 'exam';
    case Learning = 'learning';
    case Interview = 'interview';
    case Attitude = 'attitude';
    case Other = 'other';
}
