import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { Pagination } from '@/components/app/pagination';
import type { StatusTone } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { DownloadIcon, ReportIcon } from '@/lib/icons';
import exportsRoutes from '@/routes/admin/exports';
import reports from '@/routes/admin/reports';
import type { Paginated } from '@/types';
import { router } from '@inertiajs/react';

type ExportStatus = 'queued' | 'processing' | 'ready' | 'failed' | 'expired';

type ExportRow = {
    id: string;
    report: string;
    title: string;
    filters_count: number;
    status: ExportStatus;
    error_message: string | null;
    created_at: string | null;
    completed_at: string | null;
    expires_at: string | null;
};

const tones: Record<ExportStatus, StatusTone> = {
    queued: 'neutral',
    processing: 'info',
    ready: 'success',
    failed: 'danger',
    expired: 'neutral',
};

const POLL_MS = 3000;

export default function ExportsIndex({
    exports,
}: {
    exports: Paginated<ExportRow>;
}) {
    const { displayTimezone } = usePage().props;
    const pending = exports.data.some(
        (row) => row.status === 'queued' || row.status === 'processing',
    );
    const { start, stop } = usePoll(
        POLL_MS,
        { only: ['exports'] },
        { autoStart: false },
    );

    // Perbarui daftar hanya selama masih ada ekspor yang diproses.
    useEffect(() => {
        if (pending) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [pending, start, stop]);

    return (
        <>
            <Head title={t('admin.exports.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.exports.title')}
                    description={t('admin.exports.description')}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={reports.index()}>
                                {t('admin.exports.toReports')}
                            </Link>
                        </Button>
                    }
                />

                {pending && (
                    <p
                        className="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        {t('admin.exports.autoRefresh')}
                    </p>
                )}

                {exports.data.length === 0 ? (
                    <EmptyState
                        icon={ReportIcon}
                        title={t('admin.exports.empty')}
                        description={t('admin.exports.emptyDescription')}
                    />
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {exports.data.map((row) => (
                            <li
                                key={row.id}
                                className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-medium">
                                            {row.title}
                                        </p>
                                        <StatusBadge
                                            label={t(
                                                `admin.exports.status.${row.status}`,
                                            )}
                                            tone={tones[row.status]}
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {t('admin.exports.columns.requestedAt')}{' '}
                                        {formatDateTime(
                                            row.created_at,
                                            displayTimezone,
                                        )}{' '}
                                        ·{' '}
                                        {row.filters_count > 0
                                            ? t('admin.exports.filtersCount', {
                                                  count: row.filters_count,
                                              })
                                            : t('admin.exports.noFilters')}
                                        {row.status === 'ready' &&
                                            row.expires_at &&
                                            ` · ${t('admin.exports.columns.expiresAt')} ${formatDateTime(row.expires_at, displayTimezone)}`}
                                    </p>
                                    {row.status === 'failed' &&
                                        row.error_message && (
                                            <p className="text-sm text-red-700 dark:text-red-400">
                                                {row.error_message}
                                            </p>
                                        )}
                                </div>
                                {row.status === 'ready' && (
                                    <Button
                                        size="sm"
                                        className="shrink-0"
                                        onClick={() =>
                                            // Unduhan file biasa, bukan kunjungan Inertia.
                                            window.location.assign(
                                                exportsRoutes.download(row.id)
                                                    .url,
                                            )
                                        }
                                    >
                                        <DownloadIcon aria-hidden="true" />
                                        {t('admin.exports.download')}
                                        <span className="sr-only">
                                            : {row.title}
                                        </span>
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination
                    meta={exports.meta}
                    onPageChange={(page) =>
                        router.get(
                            exportsRoutes.index().url,
                            { page },
                            { preserveScroll: true },
                        )
                    }
                />
            </div>
        </>
    );
}

ExportsIndex.layout = {
    breadcrumbs: [
        { title: t('admin.reports.title'), href: reports.index() },
        { title: t('admin.exports.title'), href: exportsRoutes.index() },
    ],
};
