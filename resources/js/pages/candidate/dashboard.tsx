import { Head, usePage } from '@inertiajs/react';
import { EmptyState } from '@/components/app/empty-state';
import { RecruitmentStageBadge } from '@/components/app/status-badge';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { PositionIcon } from '@/lib/icons';
import { dashboard } from '@/routes';
import type { OwnApplication } from '@/types';

type Props = {
    candidateNumber: string | null;
    applications: OwnApplication[];
};

export default function CandidateDashboard({
    candidateNumber,
    applications,
}: Props) {
    const { auth, displayTimezone } = usePage().props;

    return (
        <>
            <Head title={t('candidate.dashboard.title')} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title={t('candidate.dashboard.greeting', {
                        name: auth.user.name,
                    })}
                    description={
                        candidateNumber
                            ? t('candidate.dashboard.candidateNumber', {
                                  number: candidateNumber,
                              })
                            : undefined
                    }
                />

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">
                        {t('candidate.dashboard.applicationsTitle')}
                    </h2>

                    {applications.length === 0 ? (
                        <EmptyState
                            title={t('candidate.dashboard.empty')}
                            description={t(
                                'candidate.dashboard.emptyDescription',
                            )}
                        />
                    ) : (
                        <div className="grid gap-3">
                            {applications.map((application) => (
                                <Card key={application.id}>
                                    <CardContent className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex items-start gap-3">
                                            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <PositionIcon
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                            </div>
                                            <div>
                                                <p className="font-medium">
                                                    {application.position}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {application.department} ·{' '}
                                                    {application.campaign}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {t(
                                                        'candidate.dashboard.appliedAt',
                                                        {
                                                            date: formatDate(
                                                                application.applied_at,
                                                                displayTimezone,
                                                            ),
                                                        },
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <RecruitmentStageBadge
                                            stage={application.stage}
                                        />
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

CandidateDashboard.layout = {
    breadcrumbs: [
        {
            title: t('candidate.dashboard.title'),
            href: dashboard(),
        },
    ],
};
