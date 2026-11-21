<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Support\ReportValues;
use Illuminate\Database\Query\Builder;

/**
 * Progres materi wajib per penugasan program, dihitung terhadap materi wajib
 * aktif saat ini (sama dengan halaman belajar dan dashboard).
 */
class LearningProgressReport extends Report
{
    public function type(): ReportType
    {
        return ReportType::LearningProgress;
    }

    public function title(): string
    {
        return __('Progres belajar');
    }

    public function description(): string
    {
        return __('Satu baris per program yang ditugaskan: jumlah materi wajib selesai dan statusnya.');
    }

    public function extraFilters(): array
    {
        return ['program'];
    }

    public function columns(): array
    {
        return [
            'candidate_number' => __('Nomor kandidat'),
            'name' => __('Nama'),
            'campaign' => __('Rekrutmen'),
            'program' => __('Program'),
            'required_completed' => __('Materi wajib selesai'),
            'required_total' => __('Total materi wajib'),
            'percent' => __('Persentase'),
            'status' => __('Status'),
            'due_at' => __('Batas waktu'),
        ];
    }

    public function query(Builder $applications, array $filters): Builder
    {
        return $this->applications($applications)
            ->join('program_assignments as pa', 'pa.recruitment_application_id', '=', 'a.id')
            ->join('learning_programs as lp', 'lp.id', '=', 'pa.learning_program_id')
            ->when($filters['program_id'] ?? null, fn ($q, int $id) => $q->where('lp.id', $id))
            ->select(['cp.candidate_number', 'u.name', 'c.name as campaign', 'lp.name as program', 'pa.due_at'])
            ->selectRaw($this->materialCount(true, null).' as required_total')
            ->selectRaw($this->materialCount(true, 'completed').' as required_completed')
            ->selectRaw($this->materialCount(false, 'touched').' as touched')
            ->orderBy('lp.name')
            ->orderBy('u.name');
    }

    public function row(object $row): array
    {
        $total = (int) $row->required_total;
        $completed = (int) $row->required_completed;

        return [
            'candidate_number' => $row->candidate_number,
            'name' => $row->name,
            'campaign' => $row->campaign,
            'program' => $row->program,
            'required_completed' => $completed,
            'required_total' => $total,
            'percent' => ReportValues::percent($completed, $total),
            'status' => ReportValues::learning($total, $completed, (int) $row->touched),
            'due_at' => ReportValues::dateTime($row->due_at),
        ];
    }
}
