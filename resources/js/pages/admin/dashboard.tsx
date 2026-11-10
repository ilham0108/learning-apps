import { Head, usePage } from '@inertiajs/react';
import { EmptyState } from '@/components/app/empty-state';
import { CampaignStatusBadge } from '@/components/app/status-badge';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { CampaignIcon, CandidatesIcon } from '@/lib/icons';
import { dashboard } from '@/routes/admin';
import type { CampaignSummary } from '@/types';

type Props = {
    campaigns: CampaignSummary[];
    candidateCount: number;
};

export default function AdminDashboard({ campaigns, candidateCount }: Props) {
    const { auth, displayTimezone } = usePage().props;
    const isSuperAdmin = auth.user.role === 'super_admin';

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
                            : t('admin.dashboard.scopeHr')
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        icon={CampaignIcon}
                        label={t('admin.dashboard.campaignCount')}
                        value={campaigns.length}
                    />
                    <StatCard
                        icon={CandidatesIcon}
                        label={t('admin.dashboard.candidateCount')}
                        value={candidateCount}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CampaignIcon
                                className="size-5"
                                aria-hidden="true"
                            />
                            {t('admin.dashboard.campaignsTitle')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {campaigns.length === 0 ? (
                            <EmptyState
                                title={t('admin.dashboard.empty')}
                                description={
                                    isSuperAdmin
                                        ? undefined
                                        : t('admin.dashboard.emptyHr')
                                }
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left text-muted-foreground">
                                        <tr>
                                            <th className="py-2 pr-4 font-medium">
                                                {t(
                                                    'admin.dashboard.columns.name',
                                                )}
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                {t(
                                                    'admin.dashboard.columns.position',
                                                )}
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                {t(
                                                    'admin.dashboard.columns.period',
                                                )}
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                {t(
                                                    'admin.dashboard.columns.status',
                                                )}
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                {t(
                                                    'admin.dashboard.columns.applications',
                                                )}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {campaigns.map((campaign) => (
                                            <tr
                                                key={campaign.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="py-3 pr-4 font-medium">
                                                    {campaign.name}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <div>
                                                        {campaign.position}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {campaign.department}
                                                    </div>
                                                </td>
                                                <td className="py-3 pr-4 whitespace-nowrap">
                                                    {formatDate(
                                                        campaign.starts_on,
                                                        displayTimezone,
                                                    )}{' '}
                                                    –{' '}
                                                    {formatDate(
                                                        campaign.ends_on,
                                                        displayTimezone,
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <CampaignStatusBadge
                                                        status={campaign.status}
                                                    />
                                                </td>
                                                <td className="py-3 text-right tabular-nums">
                                                    {campaign.applications_count ??
                                                        0}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function StatCard({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof CampaignIcon;
    label: string;
    value: number;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-4">
                <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-5" aria-hidden="true" />
                </div>
                <div>
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="text-2xl font-semibold tabular-nums">
                        {value}
                    </p>
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
