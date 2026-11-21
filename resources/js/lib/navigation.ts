import { t } from '@/lib/i18n';
import {
    AuditIcon,
    CampaignIcon,
    CandidateIcon,
    CandidatesIcon,
    DashboardIcon,
    ExamIcon,
    GradingIcon,
    LearningIcon,
    PipelineIcon,
    PositionIcon,
    QuestionBankIcon,
    ReportIcon,
    SecurityIcon,
    StaffIcon,
} from '@/lib/icons';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import auditLog from '@/routes/admin/audit-log';
import campaigns from '@/routes/admin/campaigns';
import candidates from '@/routes/admin/candidates';
import exams from '@/routes/admin/exams';
import grading from '@/routes/admin/grading';
import jobRoles from '@/routes/admin/job-roles';
import pipeline from '@/routes/admin/pipeline';
import positions from '@/routes/admin/positions';
import programs from '@/routes/admin/programs';
import questions from '@/routes/admin/questions';
import reports from '@/routes/admin/reports';
import staff from '@/routes/admin/staff';
import candidateProfile from '@/routes/candidate-profile';
import candidateExams from '@/routes/exams';
import learn from '@/routes/learn';
import type { NavItem, Permission, Role } from '@/types';

type StaffItem = NavItem & {
    /** Tampil jika pengguna memiliki salah satu hak ini; kosong = semua staf. */
    anyOf: Permission[];
};

/**
 * Menu per role dan hak jabatan. Menu hanya memudahkan navigasi; otorisasi
 * tetap diperiksa di server lewat middleware dan Policy.
 */
export function navigationFor(user: {
    role: Role;
    permissions: Permission[];
}): NavItem[] {
    if (user.role === 'candidate') {
        return [
            {
                title: t('nav.dashboard'),
                href: dashboard(),
                icon: DashboardIcon,
                exact: true,
            },
            {
                title: t('nav.learn'),
                href: learn.index(),
                icon: LearningIcon,
            },
            {
                title: t('nav.exams'),
                href: candidateExams.index(),
                icon: ExamIcon,
            },
            {
                title: t('nav.profile'),
                href: candidateProfile.edit(),
                icon: CandidateIcon,
            },
        ];
    }

    const items: StaffItem[] = [
        {
            title: t('nav.dashboard'),
            href: adminDashboard(),
            icon: DashboardIcon,
            exact: true,
            anyOf: [],
        },
        {
            title: t('nav.candidates'),
            href: candidates.index(),
            icon: CandidatesIcon,
            anyOf: [],
        },
        {
            title: t('nav.programs'),
            href: programs.index(),
            icon: LearningIcon,
            anyOf: ['learning.manage', 'learning.assign'],
        },
        {
            title: t('nav.exams'),
            href: exams.index(),
            icon: ExamIcon,
            anyOf: [
                'exams.manage',
                'exams.assign',
                'grading.grade',
                'results.release',
            ],
        },
        {
            title: t('nav.grading'),
            href: grading.index(),
            icon: GradingIcon,
            anyOf: ['grading.grade'],
        },
        {
            title: t('nav.questions'),
            href: questions.index(),
            icon: QuestionBankIcon,
            anyOf: ['exams.manage'],
        },
        {
            title: t('nav.campaigns'),
            href: campaigns.index(),
            icon: CampaignIcon,
            anyOf: [],
        },
        {
            title: t('nav.positions'),
            href: positions.index(),
            icon: PositionIcon,
            anyOf: ['campaigns.manage'],
        },
        {
            title: t('nav.pipeline'),
            href: pipeline.index(),
            icon: PipelineIcon,
            anyOf: ['pipeline.manage_default'],
        },
        {
            title: t('nav.reports'),
            href: reports.index(),
            icon: ReportIcon,
            anyOf: ['reports.view'],
        },
        {
            title: t('nav.staff'),
            href: staff.index(),
            icon: StaffIcon,
            anyOf: ['staff.manage'],
        },
        {
            title: t('nav.jobRoles'),
            href: jobRoles.index(),
            icon: SecurityIcon,
            anyOf: ['roles.manage'],
        },
        {
            title: t('nav.auditLog'),
            href: auditLog.index(),
            icon: AuditIcon,
            anyOf: ['audit.view'],
        },
    ];

    return items
        .filter(
            (item) =>
                item.anyOf.length === 0 ||
                item.anyOf.some((permission) =>
                    user.permissions.includes(permission),
                ),
        )
        .map(({ anyOf: _anyOf, ...item }) => item);
}

/** Apakah pengguna memiliki hak tertentu (untuk menampilkan tombol). */
export function can(
    user: { permissions: Permission[] },
    permission: Permission,
): boolean {
    return user.permissions.includes(permission);
}

export function homeFor(role: Role) {
    return role === 'candidate' ? dashboard() : adminDashboard();
}
