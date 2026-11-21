<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Support\ReportValues;
use Illuminate\Database\Query\Builder;

/**
 * Nilai per kategori soal dari attempt yang menentukan nilai penugasan.
 */
class CategoryScoresReport extends Report
{
    public function type(): ReportType
    {
        return ReportType::CategoryScores;
    }

    public function title(): string
    {
        return __('Nilai per kategori');
    }

    public function description(): string
    {
        return __('Poin per kategori soal untuk setiap ujian, dari percobaan yang menentukan nilai.');
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
            'exam' => __('Ujian'),
            'category' => __('Kategori'),
            'earned_points' => __('Poin diperoleh'),
            'max_points' => __('Poin maksimal'),
            'percent' => __('Persentase'),
            'grading_status' => __('Status nilai'),
        ];
    }

    public function query(Builder $applications, array $filters): Builder
    {
        return $this->applications($applications)
            ->join('exam_assignments as ea', 'ea.recruitment_application_id', '=', 'a.id')
            ->join('exams as e', 'e.id', '=', 'ea.exam_id')
            ->join('exam_attempts as t', 't.id', '=', 'ea.effective_attempt_id')
            ->join('attempt_category_scores as s', 's.exam_attempt_id', '=', 't.id')
            ->when($filters['exam_id'] ?? null, fn ($q, int $id) => $q->where('e.id', $id))
            ->select(['cp.candidate_number', 'u.name', 'e.title as exam', 's.category_name as category', 's.earned_points', 's.max_points', 't.grading_status'])
            ->orderBy('e.title')
            ->orderBy('u.name')
            ->orderBy('s.category_name');
    }

    public function row(object $row): array
    {
        return [
            'candidate_number' => $row->candidate_number,
            'name' => $row->name,
            'exam' => $row->exam,
            'category' => $row->category,
            'earned_points' => ReportValues::decimal($row->earned_points),
            'max_points' => ReportValues::decimal($row->max_points),
            'percent' => ReportValues::percent($row->earned_points, $row->max_points),
            'grading_status' => ReportValues::grading($row->grading_status),
        ];
    }
}
