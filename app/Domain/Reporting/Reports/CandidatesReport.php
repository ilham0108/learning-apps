<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Support\ReportValues;
use Illuminate\Database\Query\Builder;

/**
 * Satu baris per lamaran: identitas, tahap, progres belajar, dan ringkasan
 * hasil ujian memakai nilai sesuai kebijakan percobaan.
 */
class CandidatesReport extends Report
{
    public function type(): ReportType
    {
        return ReportType::Candidates;
    }

    public function title(): string
    {
        return __('Kandidat dan hasil ujian');
    }

    public function description(): string
    {
        return __('Satu baris per lamaran: tahap rekrutmen, progres materi wajib, dan ringkasan hasil ujian.');
    }

    public function columns(): array
    {
        return [
            'candidate_number' => __('Nomor kandidat'),
            'name' => __('Nama'),
            'email' => __('Email'),
            'phone' => __('Telepon'),
            'campaign' => __('Rekrutmen'),
            'position' => __('Posisi'),
            'stage' => __('Tahap'),
            'applied_at' => __('Melamar'),
            'learning_percent' => __('Materi wajib selesai (%)'),
            'exams_assigned' => __('Ujian ditugaskan'),
            'exams_passed' => __('Ujian lulus'),
            'average_score' => __('Rata-rata nilai'),
        ];
    }

    public function query(Builder $applications, array $filters): Builder
    {
        $effective = 'from exam_assignments ea_x join exam_attempts t_x on t_x.id = ea_x.effective_attempt_id where ea_x.recruitment_application_id = a.id';
        // Materi wajib aktif dari semua program yang ditugaskan pada lamaran ini.
        // Referensi ke `a` diletakkan di WHERE (MySQL 5.7 menolaknya di klausa ON subquery).
        $required = 'from program_assignments pa'
            .' join learning_modules mod_r on mod_r.learning_program_id = pa.learning_program_id'
            .' join learning_materials m_r on m_r.learning_module_id = mod_r.id';
        $where = ' where pa.recruitment_application_id = a.id and m_r.archived_at is null and m_r.is_required = 1';

        return $this->applications($applications)
            ->leftJoin('campaign_stages as cs', 'cs.id', '=', 'a.campaign_stage_id')
            ->select(['cp.candidate_number', 'u.name', 'u.email', 'cp.phone', 'c.name as campaign', 'pos.name as position', 'a.stage', 'cs.name as stage_name', 'a.applied_at'])
            ->selectRaw("(select count(*) {$required}{$where}) as required_total")
            ->selectRaw("(select count(*) {$required} join material_progress p_r on p_r.learning_material_id = m_r.id{$where}"
                .' and p_r.candidate_profile_id = a.candidate_profile_id and p_r.completed_at is not null) as required_completed')
            ->selectRaw('(select count(*) from exam_assignments ea_c where ea_c.recruitment_application_id = a.id) as exams_assigned')
            ->selectRaw("(select count(*) {$effective} and t_x.result = 'passed') as exams_passed")
            ->selectRaw("(select avg(t_x.score_percent) {$effective} and t_x.grading_status = 'graded') as average_score")
            ->orderBy('c.name')
            ->orderBy('u.name');
    }

    public function row(object $row): array
    {
        return [
            'candidate_number' => $row->candidate_number,
            'name' => $row->name,
            'email' => $row->email,
            'phone' => $row->phone,
            'campaign' => $row->campaign,
            'position' => $row->position,
            'stage' => ReportValues::stage($row->stage, $row->stage_name),
            'applied_at' => ReportValues::dateTime($row->applied_at),
            'learning_percent' => ReportValues::percent($row->required_completed, $row->required_total),
            'exams_assigned' => (int) $row->exams_assigned,
            'exams_passed' => (int) $row->exams_passed,
            'average_score' => ReportValues::decimal($row->average_score),
        ];
    }
}
