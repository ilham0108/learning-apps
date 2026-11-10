import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { Column, SortDirection } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { OptionSelect } from '@/components/app/option-select';
import { PageHeader } from '@/components/app/page-header';
import { Pagination } from '@/components/app/pagination';
import { SearchInput } from '@/components/app/search-input';
import {
    AccountStatusBadge,
    RecruitmentStageBadge,
} from '@/components/app/status-badge';
import { CandidateFormDialog } from '@/components/candidates/candidate-form-dialog';
import { Button } from '@/components/ui/button';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import candidates from '@/routes/admin/candidates';
import type {
    CampaignOption,
    CandidateListItem,
    Paginated,
    RecruitmentStage,
} from '@/types';

type Filters = {
    search: string | null;
    campaign: string | null;
    stage: RecruitmentStage | null;
    status: 'active' | 'inactive' | null;
    sort: string;
    direction: SortDirection;
};

type Props = {
    candidates: Paginated<CandidateListItem>;
    filters: Filters;
    campaignOptions: CampaignOption[];
    openCampaignOptions: CampaignOption[];
};

const stages: RecruitmentStage[] = [
    'invited',
    'registered',
    'under_review',
    'shortlisted',
    'hired',
    'rejected',
    'withdrawn',
];

export default function CandidatesIndex({
    candidates: page,
    filters,
    campaignOptions,
    openCampaignOptions,
}: Props) {
    const { displayTimezone } = usePage().props;
    const [createOpen, setCreateOpen] = useState(false);
    const apply = useQueryFilters(candidates.index().url, filters);
    const isFiltered =
        filters.search !== null ||
        filters.campaign !== null ||
        filters.stage !== null ||
        filters.status !== null;

    const columns: Column<CandidateListItem>[] = [
        {
            key: 'name',
            header: t('admin.candidates.columns.candidate'),
            sortKey: 'name',
            cell: (candidate) => (
                <div className="min-w-48">
                    <Link
                        href={candidates.show(candidate.id)}
                        className="font-medium text-primary hover:underline"
                    >
                        {candidate.name}
                    </Link>
                    <div className="text-xs text-muted-foreground">
                        {candidate.email}
                    </div>
                </div>
            ),
        },
        {
            key: 'number',
            header: t('admin.candidates.columns.number'),
            sortKey: 'candidate_number',
            className: 'whitespace-nowrap font-mono text-xs',
            cell: (candidate) => candidate.candidate_number,
        },
        {
            key: 'applications',
            header: t('admin.candidates.columns.applications'),
            cell: (candidate) => (
                <ul className="grid min-w-56 gap-1.5">
                    {candidate.applications.map((application) => (
                        <li
                            key={application.id}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <span className="text-xs">
                                {application.campaign}
                            </span>
                            <RecruitmentStageBadge stage={application.stage} />
                        </li>
                    ))}
                </ul>
            ),
        },
        {
            key: 'status',
            header: t('admin.candidates.columns.status'),
            cell: (candidate) => (
                <AccountStatusBadge
                    isActive={candidate.is_active}
                    isActivated={candidate.is_activated}
                />
            ),
        },
        {
            key: 'registered_on',
            header: t('admin.candidates.columns.registeredOn'),
            sortKey: 'registered_on',
            className: 'whitespace-nowrap',
            cell: (candidate) =>
                formatDate(candidate.registered_on, displayTimezone),
        },
    ];

    return (
        <>
            <Head title={t('admin.candidates.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.candidates.title')}
                    description={t('admin.candidates.description')}
                    actions={
                        <Button onClick={() => setCreateOpen(true)}>
                            <Plus aria-hidden="true" />
                            {t('admin.candidates.create')}
                        </Button>
                    }
                />

                <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap">
                    <SearchInput
                        value={filters.search}
                        onSearch={(search) => apply({ search })}
                        placeholder={t('admin.candidates.searchPlaceholder')}
                        label={t('common.search')}
                    />
                    <OptionSelect
                        id="filter-campaign"
                        value={filters.campaign}
                        onChange={(campaign) => apply({ campaign })}
                        options={campaignOptions.map((campaign) => ({
                            value: campaign.id,
                            label: campaign.name,
                        }))}
                        allowAll
                        placeholder={t('admin.candidates.filters.campaign')}
                        ariaLabel={t('admin.candidates.filters.campaign')}
                        className="lg:w-64"
                    />
                    <OptionSelect
                        id="filter-stage"
                        value={filters.stage}
                        onChange={(stage) =>
                            apply({ stage: stage as RecruitmentStage | null })
                        }
                        options={stages.map((stage) => ({
                            value: stage,
                            label: t(`status.recruitmentStage.${stage}`),
                        }))}
                        allowAll
                        ariaLabel={t('admin.candidates.filters.stage')}
                        className="lg:w-48"
                    />
                    <OptionSelect
                        id="filter-status"
                        value={filters.status}
                        onChange={(status) =>
                            apply({ status: status as Filters['status'] })
                        }
                        options={[
                            { value: 'active', label: t('common.active') },
                            { value: 'inactive', label: t('common.inactive') },
                        ]}
                        allowAll
                        ariaLabel={t('admin.candidates.filters.status')}
                        className="lg:w-40"
                    />
                    {isFiltered && (
                        <Button
                            variant="ghost"
                            onClick={() =>
                                apply({
                                    search: null,
                                    campaign: null,
                                    stage: null,
                                    status: null,
                                })
                            }
                        >
                            {t('common.resetFilters')}
                        </Button>
                    )}
                </div>

                <DataTable
                    columns={columns}
                    rows={page.data}
                    sort={filters.sort}
                    direction={filters.direction}
                    onSort={(sort, direction) => apply({ sort, direction })}
                    caption={t('admin.candidates.title')}
                    empty={
                        <EmptyState
                            title={
                                isFiltered
                                    ? t('common.noResults')
                                    : t('admin.candidates.empty')
                            }
                            description={
                                isFiltered
                                    ? t('common.noResultsDescription')
                                    : t('admin.candidates.emptyDescription')
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
                <CandidateFormDialog
                    mode="create"
                    campaignOptions={openCampaignOptions}
                    selectedCampaign={filters.campaign ?? undefined}
                    onClose={() => setCreateOpen(false)}
                />
            )}
        </>
    );
}

CandidatesIndex.layout = {
    breadcrumbs: [
        { title: t('admin.candidates.title'), href: candidates.index() },
    ],
};
