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
    PositionIcon,
    QuestionBankIcon,
    ReportIcon,
} from '@/lib/icons';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import auditLog from '@/routes/admin/audit-log';
import campaigns from '@/routes/admin/campaigns';
import candidates from '@/routes/admin/candidates';
import exams from '@/routes/admin/exams';
import grading from '@/routes/admin/grading';
import candidateProfile from '@/routes/candidate-profile';
import candidateExams from '@/routes/exams';
import positions from '@/routes/admin/positions';
import programs from '@/routes/admin/programs';
import questions from '@/routes/admin/questions';
import reports from '@/routes/admin/reports';
import learn from '@/routes/learn';
import type { NavItem, Role } from '@/types';

/**
 * Menu per role. Menu hanya memudahkan navigasi; otorisasi tetap diperiksa
 * di server lewat middleware dan Policy. Item baru ditambahkan bersama
 * halamannya di tahap berikutnya.
 */
export function navigationFor(role: Role): NavItem[] {
    if (role === 'candidate') {
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

    const staff: NavItem[] = [
        {
            title: t('nav.dashboard'),
            href: adminDashboard(),
            icon: DashboardIcon,
            exact: true,
        },
        {
            title: t('nav.candidates'),
            href: candidates.index(),
            icon: CandidatesIcon,
        },
        {
            title: t('nav.programs'),
            href: programs.index(),
            icon: LearningIcon,
        },
        {
            title: t('nav.exams'),
            href: exams.index(),
            icon: ExamIcon,
        },
        {
            title: t('nav.grading'),
            href: grading.index(),
            icon: GradingIcon,
        },
        {
            title: t('nav.questions'),
            href: questions.index(),
            icon: QuestionBankIcon,
        },
        {
            title: t('nav.campaigns'),
            href: campaigns.index(),
            icon: CampaignIcon,
        },
        {
            title: t('nav.positions'),
            href: positions.index(),
            icon: PositionIcon,
        },
        {
            title: t('nav.reports'),
            href: reports.index(),
            icon: ReportIcon,
        },
    ];

    // Audit log hanya untuk Super Admin.
    return role === 'super_admin'
        ? [
              ...staff,
              {
                  title: t('nav.auditLog'),
                  href: auditLog.index(),
                  icon: AuditIcon,
              },
          ]
        : staff;
}

export function homeFor(role: Role) {
    return role === 'candidate' ? dashboard() : adminDashboard();
}
