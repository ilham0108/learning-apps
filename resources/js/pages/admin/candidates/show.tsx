import { Head, Link, usePage } from '@inertiajs/react';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import {
    AccountStatusBadge,
    CampaignStatusBadge,
} from '@/components/app/status-badge';
import { AccountActions } from '@/components/candidates/account-actions';
import { EvaluationSection } from '@/components/candidates/evaluation-section';
import { NotesSection } from '@/components/candidates/notes-section';
import { ProgramsSection } from '@/components/candidates/programs-section';
import type {
    CandidateProgramAssignment,
    ProgramOption,
} from '@/components/candidates/programs-section';
import { StageSection } from '@/components/candidates/stage-section';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { formatDate, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { CandidateIcon, PositionIcon } from '@/lib/icons';
import campaigns from '@/routes/admin/campaigns';
import candidates from '@/routes/admin/candidates';
import type { CandidateDetail } from '@/types';

type Props = {
    candidate: CandidateDetail;
    canUpdate: boolean;
    programAssignments: Record<string, CandidateProgramAssignment[]>;
    programOptions: ProgramOption[];
};

export default function CandidateShow({
    candidate,
    canUpdate,
    programAssignments,
    programOptions,
}: Props) {
    const { displayTimezone } = usePage().props;
    const notYet = t('admin.candidates.detail.notYet');

    return (
        <>
            <Head title={candidate.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={candidate.name}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <span className="font-mono text-xs">
                                {candidate.candidate_number}
                            </span>
                            <AccountStatusBadge
                                isActive={candidate.is_active}
                                isActivated={candidate.activated_at !== null}
                            />
                        </span>
                    }
                    actions={
                        canUpdate && <AccountActions candidate={candidate} />
                    }
                />

                <div className="grid gap-6 xl:grid-cols-3">
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CandidateIcon
                                    className="size-5"
                                    aria-hidden="true"
                                />
                                {t('admin.candidates.detail.profile')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-3 text-sm">
                                <Detail
                                    label={t('common.email')}
                                    value={candidate.email}
                                />
                                <Detail
                                    label={t('admin.candidates.detail.phone')}
                                    value={
                                        candidate.phone ??
                                        t('common.notAvailable')
                                    }
                                />
                                <Detail
                                    label={t(
                                        'admin.candidates.detail.lastEducation',
                                    )}
                                    value={
                                        candidate.last_education ??
                                        t('common.notAvailable')
                                    }
                                />
                                <Detail
                                    label={t(
                                        'admin.candidates.detail.registeredOn',
                                    )}
                                    value={formatDate(
                                        candidate.registered_on,
                                        displayTimezone,
                                    )}
                                />
                                <Detail
                                    label={t(
                                        'admin.candidates.detail.activatedAt',
                                    )}
                                    value={
                                        candidate.activated_at
                                            ? formatDateTime(
                                                  candidate.activated_at,
                                                  displayTimezone,
                                              )
                                            : notYet
                                    }
                                />
                                <Detail
                                    label={t('admin.candidates.detail.consent')}
                                    value={
                                        candidate.consent_given_at
                                            ? t(
                                                  'admin.candidates.detail.consentValue',
                                                  {
                                                      date: formatDateTime(
                                                          candidate.consent_given_at,
                                                          displayTimezone,
                                                      ),
                                                      version:
                                                          candidate.consent_policy_version ??
                                                          '—',
                                                  },
                                              )
                                            : notYet
                                    }
                                />
                                <Detail
                                    label={t(
                                        'admin.candidates.detail.lastLogin',
                                    )}
                                    value={
                                        candidate.last_login_at
                                            ? formatDateTime(
                                                  candidate.last_login_at,
                                                  displayTimezone,
                                              )
                                            : t('admin.candidates.detail.never')
                                    }
                                />
                            </dl>
                        </CardContent>
                    </Card>

                    <div className="space-y-6 xl:col-span-2">
                        <h2 className="text-lg font-semibold">
                            {t('admin.candidates.detail.applications')}
                        </h2>

                        {candidate.applications.length === 0 ? (
                            <EmptyState
                                title={t(
                                    'admin.candidates.detail.noApplications',
                                )}
                            />
                        ) : (
                            candidate.applications.map((application) => (
                                <Card key={application.id}>
                                    <CardHeader>
                                        <CardTitle className="flex flex-wrap items-center gap-2">
                                            <PositionIcon
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                            <Link
                                                href={campaigns.show(
                                                    application.campaign.id,
                                                )}
                                                className="hover:underline"
                                            >
                                                {application.campaign.name}
                                            </Link>
                                            <CampaignStatusBadge
                                                status={
                                                    application.campaign.status
                                                }
                                            />
                                        </CardTitle>
                                        <CardDescription>
                                            {application.campaign.position} ·{' '}
                                            {application.campaign.department}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <StageSection
                                            application={application}
                                        />
                                        <Separator />
                                        <ProgramsSection
                                            application={application}
                                            assignments={
                                                programAssignments[
                                                    application.id
                                                ] ?? []
                                            }
                                            programOptions={programOptions}
                                        />
                                        <Separator />
                                        <NotesSection
                                            application={application}
                                        />
                                        <Separator />
                                        <EvaluationSection
                                            application={application}
                                        />
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium break-words">{value}</dd>
        </div>
    );
}

CandidateShow.layout = {
    breadcrumbs: [
        { title: t('admin.candidates.title'), href: candidates.index() },
    ],
};
