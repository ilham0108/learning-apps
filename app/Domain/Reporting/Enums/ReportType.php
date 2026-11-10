<?php

namespace App\Domain\Reporting\Enums;

enum ReportType: string
{
    case Candidates = 'candidates';
    case ExamResults = 'exam_results';
    case CategoryScores = 'category_scores';
    case LearningProgress = 'learning_progress';
    case PassRate = 'pass_rate';
    case IncompleteMaterials = 'incomplete_materials';
    case NotAttempted = 'not_attempted';
    case PendingGrading = 'pending_grading';
}
