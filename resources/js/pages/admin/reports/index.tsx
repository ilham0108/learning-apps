import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { OptionSelect } from '@/components/app/option-select';
import { PageHeader } from '@/components/app/page-header';
import { Pagination } from '@/components/app/pagination';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { t } from '@/lib/i18n';
import { DownloadIcon, ReportIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';
import exportsRoutes from '@/routes/admin/exports';
import reports from '@/routes/admin/reports';
import type { PaginationMeta, RecruitmentStage } from '@/types';

type Option = { id: string; name: string };

type ReportType =
    | 'candidates'
    | 'exam_results'
    | 'category_scores'
    | 'learning_progress'
    | 'pass_rate'
    | 'incomplete_materials'
    | 'not_attempted'
    | 'pending_grading';

type Filters = {
    period: number | null;
    campaign: string | null;
    position: string | null;
    department: string | null;
    program: string | null;
    exam: string | null;
    stage: RecruitmentStage | null;
};

type Props = {
    reports: {
        type: ReportType;
        title: string;
        description: string;
        extra_filters: ('program' | 'exam')[];
    }[];
    report: ReportType;
    columns: { key: string; label: string }[];
    rows: {
        data: Record<string, string | number | null>[];
        meta: PaginationMeta;
    };
    filters: Filters;
    options: {
        campaigns: Option[];
        positions: Option[];
        departments: Option[];
        programs: Option[];
        exams: Option[];
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

/** Angka dan persentase rata kanan agar mudah dibandingkan. */
const isNumeric = (value: string | number | null) =>
    typeof value === 'number' ||
    (typeof value === 'string' && /^\d+(\.\d+)?$/.test(value));

export default function ReportsIndex({
    reports: available,
    report,
    columns,
    rows,
    filters,
    options,
}: Props) {
    const apply = useQueryFilters(reports.index().url, { report, ...filters });
    const [exporting, setExporting] = useState(false);
    const current = available.find((item) => item.type === report);
    const extra = current?.extra_filters ?? [];
    const isFiltered = Object.values(filters).some((value) => value !== null);

    // Filter program/ujian tidak relevan untuk laporan lain, jadi dihapus saat berpindah laporan.
    const selectReport = (type: ReportType) => {
        const next = available.find((item) => item.type === type);

        apply({
            report: type,
            program: next?.extra_filters.includes('program')
                ? filters.program
                : null,
            exam: next?.extra_filters.includes('exam') ? filters.exam : null,
        });
    };

    const requestExport = () =>
        router.post(
            exportsRoutes.store().url,
            { report, ...filters },
            {
                onStart: () => setExporting(true),
                onFinish: () => setExporting(false),
            },
        );

    return (
        <>
            <Head title={t('admin.reports.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.reports.title')}
                    description={t('admin.reports.description')}
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={exportsRoutes.index()}>
                                    {t('admin.reports.myExports')}
                                </Link>
                            </Button>
                            <Button
                                onClick={requestExport}
                                disabled={exporting || rows.meta.total === 0}
                            >
                                {exporting ? (
                                    <Spinner />
                                ) : (
                                    <DownloadIcon aria-hidden="true" />
                                )}
                                {exporting
                                    ? t('admin.reports.exporting')
                                    : t('admin.reports.export')}
                            </Button>
                        </>
                    }
                />

                <section aria-label={t('admin.reports.reportLabel')}>
                    <ul
                        role="radiogroup"
                        aria-label={t('admin.reports.reportLabel')}
                        className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        {available.map((item) => {
                            const selected = item.type === report;

                            return (
                                <li key={item.type}>
                                    <button
                                        type="button"
                                        role="radio"
                                        aria-checked={selected}
                                        onClick={() => selectReport(item.type)}
                                        className={cn(
                                            'flex h-full w-full items-start gap-2 rounded-lg border p-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            selected
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:border-primary/40 hover:bg-muted/50',
                                        )}
                                    >
                                        <ReportIcon
                                            className={cn(
                                                'mt-0.5 size-4 shrink-0',
                                                selected
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground',
                                            )}
                                            aria-hidden="true"
                                        />
                                        <span className="text-sm font-medium">
                                            {item.title}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                    {current && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            {current.description}
                        </p>
                    )}
                </section>

                <section
                    aria-label={t('admin.reports.filtersLabel')}
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
                        ariaLabel={t('admin.dashboard.filters.period')}
                    />
                    <OptionSelect
                        id="campaign"
                        value={filters.campaign}
                        onChange={(campaign) => apply({ campaign })}
                        options={toOptions(options.campaigns)}
                        allowAll
                        ariaLabel={t('admin.dashboard.filters.campaign')}
                    />
                    <OptionSelect
                        id="position"
                        value={filters.position}
                        onChange={(position) => apply({ position })}
                        options={toOptions(options.positions)}
                        allowAll
                        ariaLabel={t('admin.dashboard.filters.position')}
                    />
                    <OptionSelect
                        id="department"
                        value={filters.department}
                        onChange={(department) => apply({ department })}
                        options={toOptions(options.departments)}
                        allowAll
                        ariaLabel={t('admin.dashboard.filters.department')}
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
                        ariaLabel={t('admin.dashboard.filters.stage')}
                    />
                    {extra.includes('program') && (
                        <OptionSelect
                            id="program"
                            value={filters.program}
                            onChange={(program) => apply({ program })}
                            options={toOptions(options.programs)}
                            allowAll
                            ariaLabel={t('admin.dashboard.filters.program')}
                        />
                    )}
                    {extra.includes('exam') && (
                        <OptionSelect
                            id="exam"
                            value={filters.exam}
                            onChange={(exam) => apply({ exam })}
                            options={toOptions(options.exams)}
                            allowAll
                            ariaLabel={t('admin.reports.filters.exam')}
                        />
                    )}
                </section>

                {isFiltered && (
                    <div className="-mt-3">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                apply({
                                    period: null,
                                    campaign: null,
                                    position: null,
                                    department: null,
                                    program: null,
                                    exam: null,
                                    stage: null,
                                })
                            }
                        >
                            {t('common.resetFilters')}
                        </Button>
                    </div>
                )}

                <p className="text-sm text-muted-foreground" aria-live="polite">
                    {t('admin.reports.total', { total: rows.meta.total })}
                </p>

                {rows.data.length === 0 ? (
                    <EmptyState
                        icon={ReportIcon}
                        title={t('admin.reports.empty')}
                        description={t('admin.reports.emptyDescription')}
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <caption className="sr-only">
                                {current?.title}
                            </caption>
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    {columns.map((column) => (
                                        <th
                                            key={column.key}
                                            scope="col"
                                            className="px-4 py-3 font-medium whitespace-nowrap"
                                        >
                                            {column.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.data.map((row, index) => (
                                    <tr
                                        key={`${rows.meta.current_page}-${index}`}
                                        className="border-t"
                                    >
                                        {columns.map((column) => {
                                            const value = row[column.key];

                                            return (
                                                <td
                                                    key={column.key}
                                                    className={cn(
                                                        'px-4 py-2.5 whitespace-nowrap',
                                                        isNumeric(value) &&
                                                            'text-right tabular-nums',
                                                    )}
                                                >
                                                    {value === null ||
                                                    value === ''
                                                        ? t(
                                                              'common.notAvailable',
                                                          )
                                                        : value}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination
                    meta={rows.meta}
                    onPageChange={(page) => apply({ page })}
                />
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: t('admin.reports.title'), href: reports.index() }],
};
