<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportType;

/**
 * Daftar laporan yang tersedia (rules Bagian 12), dalam urutan tampil di menu.
 */
class ReportRegistry
{
    /**
     * @return array<int, Report>
     */
    public static function all(): array
    {
        return array_map(fn (ReportType $type) => self::for($type), ReportType::cases());
    }

    public static function for(ReportType $type): Report
    {
        return match ($type) {
            ReportType::Candidates => new CandidatesReport,
            ReportType::ExamResults => new ExamResultsReport,
            ReportType::CategoryScores => new CategoryScoresReport,
            ReportType::LearningProgress => new LearningProgressReport,
            ReportType::PassRate => new PassRateReport,
            ReportType::IncompleteMaterials => new IncompleteMaterialsReport,
            ReportType::NotAttempted => new NotAttemptedReport,
            ReportType::PendingGrading => new PendingGradingReport,
        };
    }
}
