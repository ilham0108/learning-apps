import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { PageHeader } from '@/components/app/page-header';
import { CampaignFormDialog } from '@/components/campaigns/campaign-form-dialog';
import { CandidateFormDialog } from '@/components/candidates/candidate-form-dialog';
import {
    CampaignStatusBadge,
    RecruitmentStageBadge,
} from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { usePeriod } from '@/hooks/use-period';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { CampaignIcon } from '@/lib/icons';
import campaigns from '@/routes/admin/campaigns';
import candidates from '@/routes/admin/candidates';
import type {
    CampaignDetail,
    HrUserOption,
    PositionOption,
    RecruitmentStage,
} from '@/types';

type Props = {
    campaign: CampaignDetail;
    stageCounts: Partial<Record<RecruitmentStage, number>>;
    canUpdate: boolean;
    positions: PositionOption[];
    canManageTeam: boolean;
    hrOptions: HrUserOption[];
};

const stageOrder: RecruitmentStage[] = [
    'invited',
    'registered',
    'under_review',
    'shortlisted',
    'hired',
    'rejected',
    'withdrawn',
];

export default function CampaignShow({
    campaign,
    stageCounts,
    canUpdate,
    positions,
    canManageTeam,
    hrOptions,
}: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [addCandidateOpen, setAddCandidateOpen] = useState(false);
    const { displayTimezone } = usePage().props;
    const period = usePeriod();
    const [teamOpen, setTeamOpen] = useState(false);
    const total = Object.values(stageCounts).reduce(
        (sum, count) => sum + (count ?? 0),
        0,
    );

    return (
        <>
            <Head title={campaign.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={campaign.name}
                    description={
                        <CampaignStatusBadge status={campaign.status} />
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link
                                    href={candidates.index({
                                        query: { campaign: campaign.id },
                                    })}
                                >
                                    <Users aria-hidden="true" />
                                    {t('admin.campaigns.detail.viewCandidates')}
                                </Link>
                            </Button>
                            {canUpdate && campaign.status !== 'closed' && (
                                <Button
                                    variant="outline"
                                    onClick={() => setAddCandidateOpen(true)}
                                >
                                    <Plus aria-hidden="true" />
                                    {t('admin.campaigns.detail.addCandidate')}
                                </Button>
                            )}
                            {canUpdate && (
                                <Button onClick={() => setEditOpen(true)}>
                                    <Pencil aria-hidden="true" />
                                    {t('common.edit')}
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CampaignIcon
                                    className="size-5"
                                    aria-hidden="true"
                                />
                                {t('admin.campaigns.title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                <Detail
                                    label={t('admin.campaigns.detail.position')}
                                    value={campaign.position.name}
                                />
                                <Detail
                                    label={t(
                                        'admin.campaigns.detail.department',
                                    )}
                                    value={campaign.position.department}
                                />
                                <Detail
                                    label={t('admin.campaigns.detail.period')}
                                    value={period(
                                        campaign.starts_on,
                                        campaign.ends_on,
                                    )}
                                />
                                <Detail
                                    label={t(
                                        'admin.campaigns.detail.createdBy',
                                    )}
                                    value={
                                        campaign.created_by ??
                                        t('common.notAvailable')
                                    }
                                />
                                {campaign.closed_at && (
                                    <Detail
                                        label={t(
                                            'admin.campaigns.detail.closedAt',
                                        )}
                                        value={formatDateTime(
                                            campaign.closed_at,
                                            displayTimezone,
                                        )}
                                    />
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('admin.campaigns.detail.stages')}
                            </CardTitle>
                            <CardDescription>
                                {t('admin.campaigns.detail.totalApplicants')}:{' '}
                                {total}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-2 text-sm">
                                {stageOrder.map((stage) => (
                                    <li
                                        key={stage}
                                        className="flex items-center justify-between"
                                    >
                                        <RecruitmentStageBadge stage={stage} />
                                        <span className="tabular-nums">
                                            {stageCounts[stage] ?? 0}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-2">
                        <div className="space-y-1.5">
                            <CardTitle>
                                {t('admin.campaigns.team.title')}
                            </CardTitle>
                            <CardDescription>
                                {t('admin.campaigns.team.description')}
                            </CardDescription>
                        </div>
                        {canManageTeam && (
                            <Button
                                variant="outline"
                                onClick={() => setTeamOpen(true)}
                            >
                                {t('admin.campaigns.team.manage')}
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {(campaign.hr_users ?? []).length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('admin.campaigns.team.empty')}
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {campaign.hr_users?.map((user) => (
                                    <li
                                        key={user.id}
                                        className="flex flex-col py-2 sm:flex-row sm:justify-between"
                                    >
                                        <span className="font-medium">
                                            {user.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {user.email}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            {teamOpen && (
                <TeamDialog
                    campaign={campaign}
                    options={hrOptions}
                    onClose={() => setTeamOpen(false)}
                />
            )}
            {editOpen && (
                <CampaignFormDialog
                    campaign={campaign}
                    positions={positions}
                    onClose={() => setEditOpen(false)}
                />
            )}
            {addCandidateOpen && (
                <CandidateFormDialog
                    mode="create"
                    campaignOptions={[
                        {
                            id: campaign.id,
                            name: campaign.name,
                            status: campaign.status,
                        },
                    ]}
                    selectedCampaign={campaign.id}
                    onClose={() => setAddCandidateOpen(false)}
                />
            )}
        </>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}

function TeamDialog({
    campaign,
    options,
    onClose,
}: {
    campaign: CampaignDetail;
    options: HrUserOption[];
    onClose: () => void;
}) {
    const form = useForm({
        hr_users: (campaign.hr_users ?? []).map((user) => user.id),
    });

    const toggle = (id: string, checked: boolean) =>
        form.setData(
            'hr_users',
            checked
                ? [...form.data.hr_users, id]
                : form.data.hr_users.filter((value) => value !== id),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(campaigns.team.update(campaign.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-6">
                    <DialogHeader>
                        <DialogTitle>
                            {t('admin.campaigns.team.manage')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('admin.campaigns.team.dialogDescription')}
                        </DialogDescription>
                    </DialogHeader>

                    {options.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('admin.campaigns.team.noHrUsers')}
                        </p>
                    ) : (
                        <fieldset className="grid max-h-80 gap-3 overflow-y-auto">
                            <legend className="sr-only">
                                {t('admin.campaigns.team.title')}
                            </legend>
                            {options.map((user) => (
                                <div
                                    key={user.id}
                                    className="flex items-start gap-3"
                                >
                                    <Checkbox
                                        id={`hr-${user.id}`}
                                        checked={form.data.hr_users.includes(
                                            user.id,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggle(user.id, checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor={`hr-${user.id}`}
                                        className="grid gap-0.5 font-normal"
                                    >
                                        <span className="font-medium">
                                            {user.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {user.email}
                                        </span>
                                    </Label>
                                </div>
                            ))}
                        </fieldset>
                    )}
                    <InputError message={form.errors.hr_users} />

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

CampaignShow.layout = {
    breadcrumbs: [
        { title: t('admin.campaigns.title'), href: campaigns.index() },
    ],
};
