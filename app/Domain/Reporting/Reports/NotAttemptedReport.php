<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Support\ReportValues;
use Illuminate\Database\Query\Builder;

/**
 * Penugasan ujian yang belum pernah dikerjakan, beserta jadwal efektifnya
 * (jadwal khusus kandidat jika ada).
 */
class NotAttemptedReport extends Report
{
    public function type(): ReportType
    {
        return ReportType::NotAttempted;
    }

    public function title(): string
    {
        return __('Belum mengikuti ujian');
    }

    public function description(): string
    {
        return __('Ujian yang sudah ditugaskan tetapi belum pernah dimulai kandidat.');
    }

    public function extraFilters(): array
    {
        return ['exam'];
    }

    public function columns(): array
    {
        return [
            'candidate_number' => __('Nomor kandidat'),
            'name' => __('Nama'),
            'email' => __('Email'),
            'campaign' => __('Rekrutmen'),
            'exam' => __('Ujian'),
            'opens_at' => __('Dibuka'),
            'closes_at' => __('Ditutup'),
            'schedule' => __('Status jadwal'),
        ];
    }

    public function query(Builder $applications, array $filters): Builder
    {
        return $this->applications($applications)
            ->join('exam_assignments as ea', 'ea.recruitment_application_id', '=', 'a.id')
            ->join('exams as e', 'e.id', '=', 'ea.exam_id')
            ->whereNotExists(fn ($q) => $q->from('exam_attempts as t')->whereColumn('t.exam_assignment_id', 'ea.id'))
            ->when($filters['exam_id'] ?? null, fn ($q, int $id) => $q->where('e.id', $id))
            ->select(['cp.candidate_number', 'u.name', 'u.email', 'c.name as campaign', 'e.title as exam'])
            ->selectRaw('coalesce(ea.opens_at_override, e.opens_at) as opens_at')
            ->selectRaw('coalesce(ea.closes_at_override, e.closes_at) as closes_at')
            ->orderBy('e.title')
            ->orderBy('u.name');
    }

    public function row(object $row): array
    {
        $closed = $row->closes_at !== null && now()->greaterThanOrEqualTo($row->closes_at);
        $notOpen = $row->opens_at !== null && now()->lessThan($row->opens_at);

        return [
            'candidate_number' => $row->candidate_number,
            'name' => $row->name,
            'email' => $row->email,
            'campaign' => $row->campaign,
            'exam' => $row->exam,
            'opens_at' => ReportValues::dateTime($row->opens_at),
            'closes_at' => ReportValues::dateTime($row->closes_at),
            'schedule' => match (true) {
                $closed => __('Sudah ditutup'),
                $notOpen => __('Belum dibuka'),
                default => __('Masih dibuka'),
            },
        ];
    }
}
