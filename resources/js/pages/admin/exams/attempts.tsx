import { Head, Link, usePage } from '@inertiajs/react';
import type { Column } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { AttemptStatusBadge } from '@/components/app/exam-badges';
import { OptionSelect } from '@/components/app/option-select';
import { PageHeader } from '@/components/app/page-header';
import { Pagination } from '@/components/app/pagination';
import { AttemptScore } from '@/components/exams/attempt-score';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import adminAttempts from '@/routes/admin/attempts';
import exams from '@/routes/admin/exams';
import examAttempts from '@/routes/admin/exams/attempts';
import type {
    AdminAttemptRow,
    AttemptStatus,
    GradingStatus,
    Paginated,
} from '@/types';

type Filters = { status: AttemptStatus | null; grading: GradingStatus | null };

type Props = {
    exam: { id: string; title: string };
    attempts: Paginated<AdminAttemptRow>;
    filters: Filters;
};

const statuses: AttemptStatus[] = [
    'in_progress',
    'submitted',
    'auto_submitted',
    'expired',
];
const gradingStatuses: GradingStatus[] = ['pending_manual', 'graded'];

export default function ExamAttempts({ exam, attempts, filters }: Props) {
    const { displayTimezone } = usePage().props;
    const apply = useQueryFilters(examAttempts.index(exam.id).url, filters);
    const isFiltered = filters.status !== null || filters.grading !== null;

    const columns: Column<AdminAttemptRow>[] = [
        {
            key: 'candidate',
            header: t('admin.attempts.columns.candidate'),
            cell: (row) => (
                <div className="min-w-40">
                    <Link
                        href={adminAttempts.show(row.id)}
                        className="font-medium text-primary hover:underline"
                    >
                        {row.candidate.name}
                    </Link>
                    <div className="text-xs text-muted-foreground">
                        {row.candidate.candidate_number} · {row.campaign}
                    </div>
                </div>
            ),
        },
        {
            key: 'attempt',
            header: t('admin.attempts.columns.attempt'),
            className: 'tabular-nums',
            cell: (row) =>
                t('admin.attempts.attemptNumber', {
                    number: row.attempt_number,
                }),
        },
        {
            key: 'status',
            header: t('admin.attempts.columns.status'),
            cell: (row) => <AttemptStatusBadge status={row.status} />,
        },
        {
            key: 'score',
            header: t('admin.attempts.columns.score'),
            cell: (row) => <AttemptScore attempt={row} />,
        },
        {
            key: 'started',
            header: t('admin.attempts.columns.started'),
            className: 'whitespace-nowrap text-xs',
            cell: (row) => formatDateTime(row.started_at, displayTimezone),
        },
        {
            key: 'submitted',
            header: t('admin.attempts.columns.submitted'),
            className: 'whitespace-nowrap text-xs',
            cell: (row) => formatDateTime(row.submitted_at, displayTimezone),
        },
        {
            key: 'tabHidden',
            header: t('admin.attempts.columns.tabHidden'),
            className: 'text-right tabular-nums',
            cell: (row) => row.tab_hidden_count,
        },
    ];

    return (
        <>
            <Head title={t('admin.attempts.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.attempts.title')}
                    description={
                        <>
                            <Link
                                href={exams.show(exam.id)}
                                className="font-medium text-foreground hover:underline"
                            >
                                {exam.title}
                            </Link>
                            {'. '}
                            {t('admin.attempts.description')}
                        </>
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row">
                    <OptionSelect
                        id="status"
                        value={filters.status}
                        onChange={(status) =>
                            apply({ status: status as AttemptStatus | null })
                        }
                        options={statuses.map((status) => ({
                            value: status,
                            label: t(`status.attempt.${status}`),
                        }))}
                        allowAll
                        ariaLabel={t('admin.attempts.filterStatus')}
                        className="sm:w-56"
                    />
                    <OptionSelect
                        id="grading"
                        value={filters.grading}
                        onChange={(grading) =>
                            apply({ grading: grading as GradingStatus | null })
                        }
                        options={gradingStatuses.map((status) => ({
                            value: status,
                            label: t(`status.grading.${status}`),
                        }))}
                        allowAll
                        ariaLabel={t('admin.attempts.filterGrading')}
                        className="sm:w-56"
                    />
                </div>

                <DataTable
                    columns={columns}
                    rows={attempts.data}
                    caption={t('admin.attempts.title')}
                    empty={
                        <EmptyState
                            title={
                                isFiltered
                                    ? t('common.noResults')
                                    : t('admin.attempts.empty')
                            }
                            description={
                                isFiltered
                                    ? t('common.noResultsDescription')
                                    : undefined
                            }
                        />
                    }
                />

                <Pagination
                    meta={attempts.meta}
                    onPageChange={(page) => apply({ page })}
                />
            </div>
        </>
    );
}

ExamAttempts.layout = {
    breadcrumbs: [{ title: t('admin.exams.title'), href: exams.index() }],
};
