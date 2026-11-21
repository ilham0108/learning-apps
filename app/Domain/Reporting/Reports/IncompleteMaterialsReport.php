<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportType;
use Illuminate\Database\Query\Builder;

/**
 * Kandidat yang belum menyelesaikan semua materi wajib dari program yang ditugaskan.
 */
class IncompleteMaterialsReport extends LearningProgressReport
{
    public function type(): ReportType
    {
        return ReportType::IncompleteMaterials;
    }

    public function title(): string
    {
        return __('Belum menyelesaikan materi');
    }

    public function description(): string
    {
        return __('Program yang materi wajibnya belum selesai semua, beserta sisa materinya.');
    }

    public function columns(): array
    {
        return [...parent::columns(), 'remaining' => __('Sisa materi wajib')];
    }

    public function query(Builder $applications, array $filters): Builder
    {
        return parent::query($applications, $filters)->havingRaw('required_completed < required_total');
    }

    public function row(object $row): array
    {
        return [...parent::row($row), 'remaining' => (int) $row->required_total - (int) $row->required_completed];
    }
}
