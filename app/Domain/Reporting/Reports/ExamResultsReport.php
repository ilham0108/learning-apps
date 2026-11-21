<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Support\ReportValues;
use Illuminate\Database\Query\Builder;

/**
 * Rekap nilai: satu baris per penugasan ujian dengan nilai sesuai kebijakan
 * percobaan (tertinggi atau terakhir).
 */
class ExamResultsReport extends Report
{
    public function type(): ReportType
    {
        return ReportType::ExamResults;
    }

    public function title(): string
    {
        return __('Rekap nilai');
    }

    public function description(): string
    {
        return __('Satu baris per ujian yang ditugaskan, memakai nilai sesuai kebijakan percobaan ujian.');
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
            'campaign' => __('Rekrutmen'),
            'exam' => __('Ujian'),
            'attempts' => __('Percobaan dipakai'),
            'max_attempts' => __('Batas percobaan'),
            'score_percent' => __('Nilai'),
            'grading_status' => __('Status nilai'),
            'result' => __('Hasil'),
            'submitted_at' => __('Dikumpulkan'),
        ];
    }

    public function query(Builder $applications, array $filters): Builder
    {
        return $this->applications($applications)
            ->join('exam_assignments as ea', 'ea.recruitment_application_id', '=', 'a.id')
            ->join('exams as e', 'e.id', '=', 'ea.exam_id')
            ->leftJoin('exam_attempts as t', 't.id', '=', 'ea.effective_attempt_id')
            ->when($filters['exam_id'] ?? null, fn ($q, int $id) => $q->where('e.id', $id))
            ->select(['cp.candidate_number', 'u.name', 'c.name as campaign', 'e.title as exam', 't.score_percent', 't.grading_status', 't.result', 't.submitted_at'])
            ->selectRaw('(select count(*) from exam_attempts t_c where t_c.exam_assignment_id = ea.id) as attempts')
            ->selectRaw('coalesce(ea.max_attempts_override, e.max_attempts) as max_attempts')
            ->orderBy('e.title')
            ->orderBy('u.name');
    }

    public function row(object $row): array
    {
        return [
            'candidate_number' => $row->candidate_number,
            'name' => $row->name,
            'campaign' => $row->campaign,
            'exam' => $row->exam,
            'attempts' => (int) $row->attempts,
            'max_attempts' => (int) $row->max_attempts,
            'score_percent' => ReportValues::decimal($row->score_percent),
            'grading_status' => $row->grading_status === null
                ? ((int) $row->attempts > 0 ? __('Sedang dikerjakan') : __('Belum dikerjakan'))
                : ReportValues::grading($row->grading_status),
            'result' => ReportValues::result($row->result),
            'submitted_at' => ReportValues::dateTime($row->submitted_at),
        ];
    }
}
