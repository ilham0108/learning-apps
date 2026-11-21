<?php

namespace App\Domain\Access\Enums;

/**
 * Katalog hak akses staf. Setiap jabatan memiliki sebagian dari hak ini dan
 * dapat diatur oleh Super Admin atau pemegang hak `roles.manage`. Super Admin
 * selalu memiliki semua hak; kandidat tidak memiliki satu pun.
 *
 * Melihat kandidat dan lamaran dalam rekrutmen yang ditugaskan selalu
 * diizinkan untuk staf; hak di bawah ini mengatur tindakan dan perluasan lingkup.
 */
enum Permission: string
{
    // Rekrutmen
    case CampaignsViewAll = 'campaigns.view_all';
    case CampaignsManage = 'campaigns.manage';
    case CampaignsAssignTeam = 'campaigns.assign_team';
    case PipelineManageDefault = 'pipeline.manage_default';
    case PipelineManageCampaign = 'pipeline.manage_campaign';

    // Kandidat dan lamaran
    case CandidatesManage = 'candidates.manage';
    case CandidatesViewSensitive = 'candidates.view_sensitive';
    case ApplicationsMoveStage = 'applications.move_stage';
    case ApplicationsDecide = 'applications.decide';
    case NotesWrite = 'notes.write';
    case EvaluationsWrite = 'evaluations.write';

    // Pembelajaran
    case LearningManage = 'learning.manage';
    case LearningAssign = 'learning.assign';

    // Bank soal, ujian, dan penilaian
    case ExamsManage = 'exams.manage';
    case ExamsAssign = 'exams.assign';
    case GradingGrade = 'grading.grade';
    case ResultsRelease = 'results.release';

    // Laporan
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    // Sistem
    case StaffManage = 'staff.manage';
    case RolesManage = 'roles.manage';
    case AuditView = 'audit.view';

    public function group(): string
    {
        return match (explode('.', $this->value)[0]) {
            'campaigns', 'pipeline' => 'recruitment',
            'candidates', 'applications', 'notes', 'evaluations' => 'candidates',
            'learning' => 'learning',
            'exams', 'grading', 'results' => 'exams',
            'reports' => 'reports',
            default => 'system',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission) => $permission->value, self::cases());
    }

    /**
     * Hak bawaan jabatan sistem saat aplikasi dipasang. Setelah itu hak dapat
     * diubah dari halaman Jabatan.
     *
     * @return array<string, array<int, self>>
     */
    public static function defaults(): array
    {
        $recruiter = [
            self::CampaignsManage, self::PipelineManageCampaign,
            self::CandidatesManage, self::CandidatesViewSensitive,
            self::ApplicationsMoveStage, self::ApplicationsDecide, self::NotesWrite, self::EvaluationsWrite,
            self::LearningManage, self::LearningAssign,
            self::ExamsManage, self::ExamsAssign, self::GradingGrade, self::ResultsRelease,
            self::ReportsView, self::ReportsExport,
        ];

        return [
            'head_hr' => [...$recruiter, self::CampaignsViewAll, self::CampaignsAssignTeam, self::PipelineManageDefault, self::RolesManage],
            'hr_recruiter' => $recruiter,
            'interviewer' => [self::NotesWrite, self::EvaluationsWrite],
        ];
    }
}
