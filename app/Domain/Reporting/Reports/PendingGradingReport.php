<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Support\ReportValues;
use Illuminate\Database\Query\Builder;

/**
 * Pengerjaan yang masih memiliki jawaban esai atau isian singkat yang belum
 * dinilai, yang paling lama menunggu di atas.
 */
class PendingGradingReport extends Report
{
    public function type(): ReportType
    {
        return ReportType::PendingGrading;
    }

    public function title(): string
    {
        return __('Menunggu penilaian manual');
    }

    public function description(): string
    {
        return __('Pengerjaan dengan jawaban esai atau isian singkat yang belum dinilai.');
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
            'attempt_number' => __('Percobaan ke-'),
            'submitted_at' => __('Dikumpulkan'),
            'pending_answers' => __('Jawaban belum dinilai'),
            'provisional_score' => __('Nilai sementara'),
        ];
    }

    public function query(Builder $applications, array $filters): Builder
    {
        return $this->applications($applications)
            ->join('exam_assignments as ea', 'ea.recruitment_application_id', '=', 'a.id')
            ->join('exams as e', 'e.id', '=', 'ea.exam_id')
            ->join('exam_attempts as t', 't.exam_assignment_id', '=', 'ea.id')
            ->where('t.grading_status', 'pending_manual')
            ->when($filters['exam_id'] ?? null, fn ($q, int $id) => $q->where('e.id', $id))
            ->select(['cp.candidate_number', 'u.name', 'c.name as campaign', 'e.title as exam', 't.attempt_number', 't.submitted_at', 't.score_percent'])
            ->selectRaw("(select count(*) from attempt_answers aa where aa.exam_attempt_id = t.id and aa.grading_status = 'pending_manual') as pending_answers")
            ->orderBy('t.submitted_at');
    }

    public function row(object $row): array
    {
        return [
            'candidate_number' => $row->candidate_number,
            'name' => $row->name,
            'campaign' => $row->campaign,
            'exam' => $row->exam,
            'attempt_number' => (int) $row->attempt_number,
            'submitted_at' => ReportValues::dateTime($row->submitted_at),
            'pending_answers' => (int) $row->pending_answers,
            'provisional_score' => ReportValues::decimal($row->score_percent),
        ];
    }
}
