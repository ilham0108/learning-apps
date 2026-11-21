<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Support\ReportValues;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tingkat kelulusan per ujian. Persentase lulus dihitung dari penugasan yang
 * nilainya sudah final, sehingga nilai sementara tidak ikut memengaruhi.
 */
class PassRateReport extends Report
{
    public function type(): ReportType
    {
        return ReportType::PassRate;
    }

    public function title(): string
    {
        return __('Tingkat kelulusan');
    }

    public function description(): string
    {
        return __('Per ujian: jumlah ditugaskan, sudah mengerjakan, lulus, tidak lulus, menunggu penilaian, dan persentase lulus dari nilai yang sudah final.');
    }

    public function extraFilters(): array
    {
        return ['exam'];
    }

    public function columns(): array
    {
        return [
            'exam' => __('Ujian'),
            'assigned' => __('Ditugaskan'),
            'attempted' => __('Sudah mengerjakan'),
            'passed' => __('Lulus'),
            'failed' => __('Tidak lulus'),
            'pending' => __('Menunggu penilaian'),
            'pass_rate' => __('Tingkat kelulusan (%)'),
            'average_score' => __('Rata-rata nilai'),
        ];
    }

    public function query(Builder $applications, array $filters): Builder
    {
        return DB::table('exam_assignments as ea')
            ->join('exams as e', 'e.id', '=', 'ea.exam_id')
            ->leftJoin('exam_attempts as t', 't.id', '=', 'ea.effective_attempt_id')
            ->whereIn('ea.recruitment_application_id', $applications)
            ->when($filters['exam_id'] ?? null, fn ($q, int $id) => $q->where('e.id', $id))
            ->groupBy('e.id', 'e.title')
            ->select('e.title as exam')
            ->selectRaw('count(*) as assigned')
            ->selectRaw("sum(case when exists (select 1 from exam_attempts x where x.exam_assignment_id = ea.id and x.status <> 'in_progress') then 1 else 0 end) as attempted")
            ->selectRaw("sum(case when t.result = 'passed' then 1 else 0 end) as passed")
            ->selectRaw("sum(case when t.result = 'failed' then 1 else 0 end) as failed")
            ->selectRaw("sum(case when exists (select 1 from exam_attempts x where x.exam_assignment_id = ea.id and x.grading_status = 'pending_manual') then 1 else 0 end) as pending")
            ->selectRaw("avg(case when t.grading_status = 'graded' then t.score_percent end) as average_score")
            ->orderBy('e.title');
    }

    public function row(object $row): array
    {
        $passed = (int) $row->passed;
        $failed = (int) $row->failed;

        return [
            'exam' => $row->exam,
            'assigned' => (int) $row->assigned,
            'attempted' => (int) $row->attempted,
            'passed' => $passed,
            'failed' => $failed,
            'pending' => (int) $row->pending,
            'pass_rate' => ReportValues::percent($passed, $passed + $failed),
            'average_score' => ReportValues::decimal($row->average_score),
        ];
    }
}
