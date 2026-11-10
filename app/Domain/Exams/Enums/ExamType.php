<?php

namespace App\Domain\Exams\Enums;

enum ExamType: string
{
    case PreTest = 'pre_test';
    case PostTest = 'post_test';
    case GeneralKnowledge = 'general_knowledge';
    case Technical = 'technical';
    case ProductKnowledge = 'product_knowledge';
    case Sop = 'sop';
    case CaseStudy = 'case_study';
}
