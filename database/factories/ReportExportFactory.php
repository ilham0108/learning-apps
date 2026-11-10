<?php

namespace Database\Factories;

use App\Domain\Reporting\Enums\ExportFormat;
use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    protected $model = ReportExport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requested_by' => User::factory()->hr(),
            'report' => ReportType::ExamResults,
            'format' => ExportFormat::Csv,
            'filters' => [],
            'status' => ExportStatus::Queued,
        ];
    }
}
