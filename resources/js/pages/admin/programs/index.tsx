import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { Column } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { ProgramStatusBadge } from '@/components/app/learning-badges';
import { OptionSelect } from '@/components/app/option-select';
import { PageHeader } from '@/components/app/page-header';
import { Pagination } from '@/components/app/pagination';
import { SearchInput } from '@/components/app/search-input';
import { ProgramFormDialog } from '@/components/programs/program-form-dialog';
import { Button } from '@/components/ui/button';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { t } from '@/lib/i18n';
import programs from '@/routes/admin/programs';
import type {
    Paginated,
    PositionOption,
    ProgramStatus,
    ProgramSummary,
} from '@/types';

type Filters = { search: string | null; status: ProgramStatus | null };

type Props = {
    programs: Paginated<ProgramSummary>;
    filters: Filters;
    positions: PositionOption[];
};

const statuses: ProgramStatus[] = ['draft', 'published', 'archived'];

export default function ProgramsIndex({
    programs: page,
    filters,
    positions,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const apply = useQueryFilters(programs.index().url, filters);
    const isFiltered = filters.search !== null || filters.status !== null;

    const columns: Column<ProgramSummary>[] = [
        {
            key: 'name',
            header: t('admin.programs.columns.name'),
            cell: (program) => (
                <div className="min-w-48">
                    <Link
                        href={programs.show(program.id)}
                        className="font-medium text-primary hover:underline"
                    >
                        {program.name}
                    </Link>
                    {program.category && (
                        <div className="text-xs text-muted-foreground">
                            {program.category}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'target',
            header: t('admin.programs.columns.target'),
            cell: (program) =>
                program.target_position ??
                t('admin.programs.fields.targetPositionPlaceholder'),
        },
        {
            key: 'content',
            header: t('admin.programs.columns.content'),
            className: 'whitespace-nowrap',
            cell: (program) =>
                t('admin.programs.contentSummary', {
                    modules: program.modules_count ?? 0,
                    materials: program.materials_count ?? 0,
                }),
        },
        {
            key: 'assignments',
            header: t('admin.programs.columns.assignments'),
            className: 'text-right tabular-nums',
            cell: (program) => program.assignments_count ?? 0,
        },
        {
            key: 'status',
            header: t('admin.programs.columns.status'),
            cell: (program) => <ProgramStatusBadge status={program.status} />,
        },
    ];

    return (
        <>
            <Head title={t('admin.programs.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.programs.title')}
                    description={t('admin.programs.description')}
                    actions={
                        <Button onClick={() => setCreateOpen(true)}>
                            <Plus aria-hidden="true" />
                            {t('admin.programs.create')}
                        </Button>
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row">
                    <SearchInput
                        value={filters.search}
                        onSearch={(search) => apply({ search })}
                        placeholder={t('admin.programs.searchPlaceholder')}
                        label={t('common.search')}
                    />
                    <OptionSelect
                        id="status"
                        value={filters.status}
                        onChange={(status) =>
                            apply({ status: status as ProgramStatus | null })
                        }
                        options={statuses.map((status) => ({
                            value: status,
                            label: t(`status.program.${status}`),
                        }))}
                        allowAll
                        ariaLabel={t('admin.programs.fields.status')}
                        className="sm:w-48"
                    />
                </div>

                <DataTable
                    columns={columns}
                    rows={page.data}
                    caption={t('admin.programs.title')}
                    empty={
                        <EmptyState
                            title={
                                isFiltered
                                    ? t('common.noResults')
                                    : t('admin.programs.empty')
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

            {createOpen && (
                <ProgramFormDialog
                    program={null}
                    positions={positions}
                    onClose={() => setCreateOpen(false)}
                />
            )}
        </>
    );
}

ProgramsIndex.layout = {
    breadcrumbs: [{ title: t('admin.programs.title'), href: programs.index() }],
};
