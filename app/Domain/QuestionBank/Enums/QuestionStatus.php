<?php

namespace App\Domain\QuestionBank\Enums;

enum QuestionStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
