<?php

namespace App\Domain\Exams\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
