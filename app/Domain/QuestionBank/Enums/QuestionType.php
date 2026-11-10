<?php

namespace App\Domain\QuestionBank\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case MultipleSelect = 'multiple_select';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';
    case Essay = 'essay';
    case CaseStudy = 'case_study';

    public function hasOptions(): bool
    {
        return in_array($this, [self::MultipleChoice, self::MultipleSelect, self::TrueFalse], true);
    }

    public function isManuallyGraded(): bool
    {
        return $this === self::Essay;
    }

    /**
     * Soal studi kasus hanya berisi teks kasus; poinnya ada di sub-soal.
     */
    public function isContainer(): bool
    {
        return $this === self::CaseStudy;
    }
}
