<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Enums\ExportFormat;
use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Jobs\GenerateReportExport;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Reports\ReportRegistry;
use App\Domain\Reporting\Support\ReportFilters;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;

/**
 * Mencatat permintaan ekspor, menulis audit log, dan mengirim pembuatan file
 * ke antrean. Hanya filter yang relevan untuk laporan tersebut yang disimpan.
 */
class RequestReportExport
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $filters  Filter berisi public_id.
     */
    public function __invoke(User $user, ReportType $type, array $filters): ReportExport
    {
        $report = ReportRegistry::for($type);
        $filters = ReportFilters::clean($filters);

        foreach (['program', 'exam'] as $extra) {
            if (! in_array($extra, $report->extraFilters(), true)) {
                unset($filters[$extra]);
            }
        }

        $export = ReportExport::query()->create([
            'requested_by' => $user->id,
            'report' => $type,
            'format' => ExportFormat::Csv,
            'filters' => $filters,
            'status' => ExportStatus::Queued,
        ]);

        $this->logger->log('export.created', $export, ['report' => $type->value, 'filters' => $filters], actor: $user);

        GenerateReportExport::dispatch($export->id);

        return $export->refresh();
    }
}
