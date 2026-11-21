import { Head, Link, router, usePage } from '@inertiajs/react';
import type { Column } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { Pagination } from '@/components/app/pagination';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { GradingIcon } from '@/lib/icons';
import adminAttempts from '@/routes/admin/attempts';
import grading from '@/routes/admin/grading';
import type { AdminAttemptRow, Paginated } from '@/types';

type Row = AdminAttemptRow & { exam: string; pending_count: number };

type Props = { attempts: Paginated<Row> };

export default function GradingIndex({ attempts }: Props) {
    const { displayTimezone } = usePage().props;

    const columns: Column<Row>[] = [
        {
            key: 'candidate',
            header: t('admin.grading.columns.candidate'),
            cell: (row) => (
                <div className="min-w-40">
                    <div className="font-medium">{row.candidate.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {row.candidate.candidate_number} · {row.campaign}
                    </div>
                </div>
            ),
        },
        {
            key: 'exam',
            header: t('admin.grading.columns.exam'),
            cell: (row) => (
                <div className="min-w-40">
                    {row.exam}
                    <div className="text-xs text-muted-foreground">
                        {t('admin.attempts.attemptNumber', {
                            number: row.attempt_number,
                        })}
                    </div>
                </div>
            ),
        },
        {
            key: 'submitted',
            header: t('admin.grading.columns.submitted'),
            className: 'whitespace-nowrap text-xs',
            cell: (row) => formatDateTime(row.submitted_at, displayTimezone),
        },
        {
            key: 'pending',
            header: t('admin.grading.columns.pending'),
            className: 'whitespace-nowrap tabular-nums',
            cell: (row) =>
                t('admin.grading.pendingCount', { count: row.pending_count }),
        },
        {
            key: 'action',
            header: t('common.actions'),
            className: 'text-right',
            cell: (row) => (
                <Button asChild size="sm">
                    <Link href={adminAttempts.show(row.id)}>
                        <GradingIcon aria-hidden="true" />
                        {t('admin.grading.open')}
                    </Link>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title={t('admin.grading.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.grading.title')}
                    description={t('admin.grading.description')}
                />

                <DataTable
                    columns={columns}
                    rows={attempts.data}
                    caption={t('admin.grading.title')}
                    empty={
                        <EmptyState
                            icon={GradingIcon}
                            title={t('admin.grading.empty')}
                            description={t('admin.grading.emptyDescription')}
                        />
                    }
                />

                <Pagination
                    meta={attempts.meta}
                    onPageChange={(page) =>
                        router.get(
                            grading.index().url,
                            { page },
                            { preserveScroll: true },
                        )
                    }
                />
            </div>
        </>
    );
}

GradingIndex.layout = {
    breadcrumbs: [{ title: t('admin.grading.title'), href: grading.index() }],
};
