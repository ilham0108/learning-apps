import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { Column } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { OptionSelect } from '@/components/app/option-select';
import { PageHeader } from '@/components/app/page-header';
import { Pagination } from '@/components/app/pagination';
import { SearchInput } from '@/components/app/search-input';
import { CampaignStatusBadge } from '@/components/app/status-badge';
import { CampaignFormDialog } from '@/components/campaigns/campaign-form-dialog';
import { Button } from '@/components/ui/button';
import { usePeriod } from '@/hooks/use-period';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { t } from '@/lib/i18n';
import campaigns from '@/routes/admin/campaigns';
import type {
    CampaignStatus,
    CampaignSummary,
    Paginated,
    PositionOption,
} from '@/types';

type Filters = {
    search: string | null;
    status: CampaignStatus | null;
};

type Props = {
    campaigns: Paginated<CampaignSummary>;
    filters: Filters;
    canCreate: boolean;
    positions: PositionOption[];
};

const statuses: CampaignStatus[] = ['draft', 'open', 'closed'];

export default function CampaignsIndex({
    campaigns: page,
    filters,
    canCreate,
    positions,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const apply = useQueryFilters(campaigns.index().url, filters);
    const period = usePeriod();
    const isFiltered = filters.search !== null || filters.status !== null;

    const columns: Column<CampaignSummary>[] = [
        {
            key: 'name',
            header: t('admin.dashboard.columns.name'),
            cell: (campaign) => (
                <Link
                    href={campaigns.show(campaign.id)}
                    className="font-medium text-primary hover:underline"
                >
                    {campaign.name}
                </Link>
            ),
        },
        {
            key: 'position',
            header: t('admin.dashboard.columns.position'),
            cell: (campaign) => (
                <>
                    <div>{campaign.position}</div>
                    <div className="text-xs text-muted-foreground">
                        {campaign.department}
                    </div>
                </>
            ),
        },
        {
            key: 'period',
            header: t('admin.dashboard.columns.period'),
            className: 'whitespace-nowrap',
            cell: (campaign) => period(campaign.starts_on, campaign.ends_on),
        },
        {
            key: 'status',
            header: t('admin.dashboard.columns.status'),
            cell: (campaign) => (
                <CampaignStatusBadge status={campaign.status} />
            ),
        },
        {
            key: 'applications',
            header: t('admin.dashboard.columns.applications'),
            className: 'text-right tabular-nums',
            cell: (campaign) => campaign.applications_count ?? 0,
        },
    ];

    return (
        <>
            <Head title={t('admin.campaigns.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.campaigns.title')}
                    description={t('admin.campaigns.description')}
                    actions={
                        canCreate && (
                            <Button onClick={() => setCreateOpen(true)}>
                                <Plus aria-hidden="true" />
                                {t('admin.campaigns.create')}
                            </Button>
                        )
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row">
                    <SearchInput
                        value={filters.search}
                        onSearch={(search) => apply({ search })}
                        placeholder={t('admin.campaigns.searchPlaceholder')}
                        label={t('common.search')}
                    />
                    <OptionSelect
                        id="status"
                        value={filters.status}
                        onChange={(status) =>
                            apply({ status: status as CampaignStatus | null })
                        }
                        options={statuses.map((status) => ({
                            value: status,
                            label: t(`status.campaign.${status}`),
                        }))}
                        allowAll
                        ariaLabel={t('admin.campaigns.fields.status')}
                        className="sm:w-48"
                    />
                </div>

                <DataTable
                    columns={columns}
                    rows={page.data}
                    caption={t('admin.campaigns.title')}
                    empty={
                        <EmptyState
                            title={
                                isFiltered
                                    ? t('common.noResults')
                                    : t('admin.campaigns.empty')
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
                <CampaignFormDialog
                    campaign={null}
                    positions={positions}
                    onClose={() => setCreateOpen(false)}
                />
            )}
        </>
    );
}

CampaignsIndex.layout = {
    breadcrumbs: [
        { title: t('admin.campaigns.title'), href: campaigns.index() },
    ],
};
