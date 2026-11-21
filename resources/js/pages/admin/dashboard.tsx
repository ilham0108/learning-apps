import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { OptionSelect } from '@/components/app/option-select';
import { ProgressBar } from '@/components/app/progress-bar';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    CalendarIcon,
    CandidatesIcon,
    CompletedIcon,
    ErrorIcon,
    ExamIcon,
    GradingIcon,
    HistoryIcon,
    LearningIcon,
    ReportIcon,
    TimerIcon,
} from '@/lib/icons';
import { dashboard } from '@/routes/admin';
import candidates from '@/routes/admin/candidates';
import exams from '@/routes/admin/exams';
import grading from '@/routes/admin/grading';
import type { RecruitmentStage } from '@/types';

type Option = { id: string; name: string };

type Filters = {
    period: number | null;
    campaign: string | null;
    position: string | null;
    department: string | null;
    program: string | null;
    stage: RecruitmentStage | null;
};

type Props = {
    stats: {
        total_candidates: number;
        new_candidates: number;
        learning_candidates: number;
        material_completion_percent: number | null;
        in_exam_candidates: number;
        passed: number;
        failed: number;
        pending_grading: number;
        average_score: string | null;
    };
    closingExams: {
        id: string;
        title: string;
        closes_at: string;
        assigned: number;
        not_started: number;
    }[];
    activity: {
        type: 'stage_changed' | 'exam_submitted' | 'material_completed';
        candidate: { id: string; name: string };
        detail: string | null;
        occurred_at: string;
    }[];
    filters: Filters;
    options: {
        campaigns: Option[];
        positions: Option[];
        departments: Option[];
        programs: Option[];
        periods: number[];
    };
};

const stages: RecruitmentStage[] = [
    'invited',
    'registered',
    'in_process',
    'rejected',
    'hired',
    'withdrawn',
];

const toOptions = (items: Option[]) =>
    items.map((item) => ({ value: item.id, label: item.name }));

export default function AdminDashboard({
    stats,
    closingExams,
    activity,
    filters,
    options,
}: Props) {
    const { auth, displayTimezone } = usePage().props;
    const isSuperAdmin = auth.user.role === 'super_admin';
    const apply = useQueryFilters(dashboard().url, filters);
    const isFiltered = Object.values(filters).some((value) => value !== null);

    // Detail perubahan tahap sudah berupa nama tahap yang tersimpan di riwayat.
    const activityText = (item: Props['activity'][number]) =>
        t(`admin.dashboard.activity.${item.type}`, {
            detail: item.detail ?? '',
        });

    return (
        <>
            <Head title={t('admin.dashboard.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title={t('admin.dashboard.greeting', {
                        name: auth.user.name,
                    })}
                    description={
                        isSuperAdmin
                            ? t('admin.dashboard.scopeAll')
                            : options.campaigns.length === 0
                              ? t('admin.dashboard.emptyHr')
                              : t('admin.dashboard.scopeHr')
                    }
                />

                <section
                    aria-label={t('admin.dashboard.filters.label')}
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6"
                >
                    <OptionSelect
                        id="period"
                        value={
                            filters.period === null
                                ? null
                                : String(filters.period)
                        }
                        onChange={(period) =>
                            apply({
                                period: period === null ? null : Number(period),
                            })
                        }
                        options={options.periods.map((days) => ({
                            value: String(days),
                            label: t('admin.dashboard.filters.periodDays', {
                                count: days,
                            }),
                        }))}
                        allowAll
                        placeholder={t('admin.dashboard.filters.periodAll')}
                        ariaLabel={t('admin.dashboard.filters.period')}
                    />
                    <OptionSelect
                        id="campaign"
                        value={filters.campaign}
                        onChange={(campaign) => apply({ campaign })}
                        options={toOptions(options.campaigns)}
                        allowAll
                        placeholder={t('admin.dashboard.filters.allCampaigns')}
                        ariaLabel={t('admin.dashboard.filters.campaign')}
                    />
                    <OptionSelect
                        id="position"
                        value={filters.position}
                        onChange={(position) => apply({ position })}
                        options={toOptions(options.positions)}
                        allowAll
                        placeholder={t('admin.dashboard.filters.allPositions')}
                        ariaLabel={t('admin.dashboard.filters.position')}
                    />
                    <OptionSelect
                        id="department"
                        value={filters.department}
                        onChange={(department) => apply({ department })}
                        options={toOptions(options.departments)}
                        allowAll
                        placeholder={t(
                            'admin.dashboard.filters.allDepartments',
                        )}
                        ariaLabel={t('admin.dashboard.filters.department')}
                    />
                    <OptionSelect
                        id="program"
                        value={filters.program}
                        onChange={(program) => apply({ program })}
                        options={toOptions(options.programs)}
                        allowAll
                        placeholder={t('admin.dashboard.filters.allPrograms')}
                        ariaLabel={t('admin.dashboard.filters.program')}
                    />
                    <OptionSelect
                        id="stage"
                        value={filters.stage}
                        onChange={(stage) =>
                            apply({ stage: stage as RecruitmentStage | null })
                        }
                        options={stages.map((stage) => ({
                            value: stage,
                            label: t(`status.recruitmentStage.${stage}`),
                        }))}
                        allowAll
                        placeholder={t('admin.dashboard.filters.allStages')}
                        ariaLabel={t('admin.dashboard.filters.stage')}
                    />
                </section>
                {isFiltered && (
                    <div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={dashboard()} preserveScroll>
                                {t('common.resetFilters')}
                            </Link>
                        </Button>
                    </div>
                )}

                <section className="space-y-3">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        {t('admin.dashboard.candidatesTitle')}
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatTile
                            icon={CandidatesIcon}
                            label={t('admin.dashboard.stats.total')}
                            value={stats.total_candidates}
                        />
                        <StatTile
                            icon={CalendarIcon}
                            label={t('admin.dashboard.stats.new')}
                            value={stats.new_candidates}
                            hint={t('admin.dashboard.stats.newHint')}
                        />
                        <StatTile
                            icon={LearningIcon}
                            label={t('admin.dashboard.stats.learning')}
                            value={stats.learning_candidates}
                            hint={t('admin.dashboard.stats.learningHint')}
                        />
                        <StatTile
                            icon={TimerIcon}
                            label={t('admin.dashboard.stats.inExam')}
                            value={stats.in_exam_candidates}
                            hint={t('admin.dashboard.stats.inExamHint')}
                        />
                    </div>
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        {t('admin.dashboard.resultsTitle')}
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatTile
                            icon={CompletedIcon}
                            label={t('admin.dashboard.stats.passed')}
                            value={stats.passed}
                            hint={t('admin.dashboard.stats.resultHint')}
                        />
                        <StatTile
                            icon={ErrorIcon}
                            label={t('admin.dashboard.stats.failed')}
                            value={stats.failed}
                            hint={t('admin.dashboard.stats.resultHint')}
                        />
                        <StatTile
                            icon={GradingIcon}
                            label={t('admin.dashboard.stats.pending')}
                            value={stats.pending_grading}
                            hint={t('admin.dashboard.stats.pendingHint')}
                            action={
                                stats.pending_grading > 0 && (
                                    <Link
                                        href={grading.index()}
                                        className="text-xs font-medium text-primary hover:underline"
                                    >
                                        {t(
                                            'admin.dashboard.stats.pendingAction',
                                        )}
                                    </Link>
                                )
                            }
                        />
                        <StatTile
                            icon={ReportIcon}
                            label={t('admin.dashboard.stats.average')}
                            value={
                                stats.average_score ?? t('common.notAvailable')
                            }
                            hint={t('admin.dashboard.stats.averageHint')}
                        />
                    </div>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <LearningIcon
                                className="size-5"
                                aria-hidden="true"
                            />
                            {t('admin.dashboard.completion.title')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {stats.material_completion_percent === null ? (
                            <p className="text-sm text-muted-foreground">
                                {t('admin.dashboard.completion.empty')}
                            </p>
                        ) : (
                            <ProgressBar
                                percent={stats.material_completion_percent}
                                label={t('admin.dashboard.completion.value', {
                                    percent: stats.material_completion_percent,
                                })}
                            />
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ExamIcon
                                    className="size-5"
                                    aria-hidden="true"
                                />
                                {t('admin.dashboard.closing.title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {closingExams.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('admin.dashboard.closing.empty')}
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {closingExams.map((exam) => (
                                        <li
                                            key={exam.id}
                                            className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0"
                                        >
                                            <Link
                                                href={exams.show(exam.id)}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {exam.title}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {t(
                                                    'admin.dashboard.closing.closesAt',
                                                    {
                                                        date: formatDateTime(
                                                            exam.closes_at,
                                                            displayTimezone,
                                                        ),
                                                    },
                                                )}
                                            </p>
                                            <p className="text-sm">
                                                {t(
                                                    'admin.dashboard.closing.summary',
                                                    {
                                                        assigned: exam.assigned,
                                                        notStarted:
                                                            exam.not_started,
                                                    },
                                                )}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <HistoryIcon
                                    className="size-5"
                                    aria-hidden="true"
                                />
                                {t('admin.dashboard.activity.title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {activity.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('admin.dashboard.activity.empty')}
                                </p>
                            ) : (
                                <ol className="space-y-3">
                                    {activity.map((item, index) => (
                                        <li
                                            key={`${item.occurred_at}-${index}`}
                                            className="border-l-2 pl-3 text-sm"
                                        >
                                            <Link
                                                href={candidates.show(
                                                    item.candidate.id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {item.candidate.name}
                                            </Link>{' '}
                                            <span className="text-muted-foreground">
                                                {activityText(item)}
                                            </span>
                                            <p className="text-xs text-muted-foreground">
                                                {formatDateTime(
                                                    item.occurred_at,
                                                    displayTimezone,
                                                )}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

/** Tile angka: label, nilai, dan keterangan cara menghitungnya. */
function StatTile({
    icon: Icon,
    label,
    value,
    hint,
    action,
}: {
    icon: LucideIcon;
    label: string;
    value: number | string;
    hint?: string;
    action?: ReactNode;
}) {
    return (
        <Card>
            <CardContent className="flex items-start gap-4">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-5" aria-hidden="true" />
                </div>
                <div className="min-w-0 space-y-0.5">
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="text-2xl font-semibold tabular-nums">
                        {typeof value === 'number'
                            ? value.toLocaleString('id-ID')
                            : value}
                    </p>
                    {hint && (
                        <p className="text-xs text-muted-foreground">{hint}</p>
                    )}
                    {action}
                </div>
            </CardContent>
        </Card>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: t('admin.dashboard.title'),
            href: dashboard(),
        },
    ],
};
