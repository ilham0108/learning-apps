import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { Column } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { ExamStatusBadge, LockedBadge } from '@/components/app/exam-badges';
import { OptionSelect } from '@/components/app/option-select';
import { PageHeader } from '@/components/app/page-header';
import { Pagination } from '@/components/app/pagination';
import { SearchInput } from '@/components/app/search-input';
import { Button } from '@/components/ui/button';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import exams from '@/routes/admin/exams';
import type { ExamDetail, ExamStatus, Paginated } from '@/types';

type Filters = { search: string | null; status: ExamStatus | null };

type Props = {
    exams: Paginated<ExamDetail>;
    filters: Filters;
};

const statuses: ExamStatus[] = ['draft', 'published', 'archived'];

export default function ExamsIndex({ exams: page, filters }: Props) {
    const { displayTimezone } = usePage().props;
    const apply = useQueryFilters(exams.index().url, filters);
    const isFiltered = filters.search !== null || filters.status !== null;

    const columns: Column<ExamDetail>[] = [
        {
            key: 'title',
            header: t('admin.exams.columns.title'),
            cell: (exam) => (
                <div className="min-w-48">
                    <Link
                        href={exams.show(exam.id)}
                        className="font-medium text-primary hover:underline"
                    >
                        {exam.title}
                    </Link>
                    <div className="text-xs text-muted-foreground">
                        {t(`status.examType.${exam.type}`)}
                    </div>
                </div>
            ),
        },
        {
            key: 'questions',
            header: t('admin.exams.columns.questions'),
            className: 'whitespace-nowrap',
            cell: (exam) =>
                t('admin.exams.questionsSummary', {
                    count: exam.questions_count ?? 0,
                    points: exam.total_points ?? '0.00',
                }),
        },
        {
            key: 'schedule',
            header: t('admin.exams.columns.schedule'),
            className: 'whitespace-nowrap text-xs',
            cell: (exam) =>
                exam.opens_at || exam.closes_at ? (
                    <>
                        <div>
                            {formatDateTime(exam.opens_at, displayTimezone)}
                        </div>
                        <div>
                            {formatDateTime(exam.closes_at, displayTimezone)}
                        </div>
                    </>
                ) : (
                    t('admin.exams.summary.noSchedule')
                ),
        },
        {
            key: 'assignments',
            header: t('admin.exams.columns.assignments'),
            className: 'text-right tabular-nums',
            cell: (exam) => exam.assignments_count ?? 0,
        },
        {
            key: 'status',
            header: t('admin.exams.columns.status'),
            cell: (exam) => (
                <div className="flex flex-wrap gap-1">
                    <ExamStatusBadge status={exam.status} />
                    {exam.is_locked && <LockedBadge />}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={t('admin.exams.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.exams.title')}
                    description={t('admin.exams.description')}
                    actions={
                        <Button asChild>
                            <Link href={exams.create()}>
                                <Plus aria-hidden="true" />
                                {t('admin.exams.create')}
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row">
                    <SearchInput
                        value={filters.search}
                        onSearch={(search) => apply({ search })}
                        placeholder={t('admin.exams.searchPlaceholder')}
                        label={t('common.search')}
                    />
                    <OptionSelect
                        id="status"
                        value={filters.status}
                        onChange={(status) =>
                            apply({ status: status as ExamStatus | null })
                        }
                        options={statuses.map((status) => ({
                            value: status,
                            label: t(`status.exam.${status}`),
                        }))}
                        allowAll
                        ariaLabel={t('admin.exams.fields.status')}
                        className="sm:w-48"
                    />
                </div>

                <DataTable
                    columns={columns}
                    rows={page.data}
                    caption={t('admin.exams.title')}
                    empty={
                        <EmptyState
                            title={
                                isFiltered
                                    ? t('common.noResults')
                                    : t('admin.exams.empty')
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
                    meta={page.meta}
                    onPageChange={(pageNumber) => apply({ page: pageNumber })}
                />
            </div>
        </>
    );
}

ExamsIndex.layout = {
    breadcrumbs: [{ title: t('admin.exams.title'), href: exams.index() }],
};
