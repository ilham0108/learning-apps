import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import {
    AttemptResultBadge,
    ExamProgressBadge,
} from '@/components/app/exam-badges';
import { LearningStatusBadge } from '@/components/app/learning-badges';
import { ProgressBar } from '@/components/app/progress-bar';
import type { StatusTone } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import {
    ClosingCountdown,
    NextStepCard,
} from '@/components/candidate-dashboard/next-step-card';
import { StageTimeline } from '@/components/candidate-dashboard/stage-timeline';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useServerNow } from '@/hooks/use-server-now';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    ExamIcon,
    LearningIcon,
    materialTypeIcons,
    PositionIcon,
    ReportIcon,
} from '@/lib/icons';
import type { DashboardExam, DashboardMaterial } from '@/lib/next-step';
import { pickNextStep } from '@/lib/next-step';
import { dashboard } from '@/routes';
import attempts from '@/routes/attempts';
import candidateExams from '@/routes/exams';
import learn from '@/routes/learn';
import type {
    AttemptSummary,
    OwnApplication,
    ProfileCompleteness,
    ProgressSummary,
} from '@/types';

type EvaluationProgress = 'pending' | 'in_progress' | 'final';

type Props = {
    candidateNumber: string | null;
    applications: (OwnApplication & {
        evaluation_status: EvaluationProgress;
    })[];
    programs: { id: string; name: string; progress: ProgressSummary }[];
    nextMaterials: DashboardMaterial[];
    exams: DashboardExam[];
    results: (AttemptSummary & { exam: string })[];
    serverNow: string;
    profileCompleteness: ProfileCompleteness;
};

const evaluationTones: Record<EvaluationProgress, StatusTone> = {
    pending: 'neutral',
    in_progress: 'warning',
    final: 'success',
};

/** Kartu yang seluruh permukaannya bisa diklik, dengan efek hover dan fokus yang jelas. */
function CardLink({
    href,
    label,
    children,
}: {
    href: Parameters<typeof Link>[0]['href'];
    label?: string;
    children: ReactNode;
}) {
    return (
        <Link
            href={href}
            aria-label={label}
            className="group flex items-center gap-3 rounded-lg border bg-card p-4 transition-colors hover:border-primary/50 hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <div className="min-w-0 flex-1">{children}</div>
            <ChevronRight
                className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                aria-hidden="true"
            />
        </Link>
    );
}

function Section({
    icon: Icon,
    title,
    more,
    children,
}: {
    icon: typeof ExamIcon;
    title: string;
    more?: { label: string; href: Parameters<typeof Link>[0]['href'] };
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-2">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Icon className="size-5" aria-hidden="true" />
                    {title}
                </CardTitle>
                {more && (
                    <Link
                        href={more.href}
                        className="text-sm text-primary hover:underline"
                    >
                        {more.label}
                    </Link>
                )}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export default function CandidateDashboard({
    candidateNumber,
    applications,
    programs,
    nextMaterials,
    exams,
    results,
    serverNow,
    profileCompleteness,
}: Props) {
    const { auth, displayTimezone } = usePage().props;
    const now = useServerNow(serverNow);
    const step = pickNextStep(exams, nextMaterials, profileCompleteness);
    const featuredExam = 'exam' in step ? step.exam.id : null;
    const otherExams = exams.filter(
        (exam) =>
            exam.id !== featuredExam &&
            exam.availability !== 'unavailable' &&
            exam.progress !== 'completed',
    );

    return (
        <>
            <Head title={t('candidate.dashboard.title')} />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
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

                <NextStepCard step={step} now={now} />

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
                                    <CardContent className="space-y-5">
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
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
                                                        {application.department}{' '}
                                                        · {application.campaign}
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
                                            <StatusBadge
                                                label={t(
                                                    `candidate.dashboard.evaluation.${application.evaluation_status}`,
                                                )}
                                                tone={
                                                    evaluationTones[
                                                        application
                                                            .evaluation_status
                                                    ]
                                                }
                                            />
                                        </div>
                                        <StageTimeline
                                            stage={application.stage}
                                            position={application.position}
                                        />
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Section
                        icon={LearningIcon}
                        title={t('candidate.dashboard.programsTitle')}
                        more={{
                            label: t('candidate.dashboard.allPrograms'),
                            href: learn.index(),
                        }}
                    >
                        {programs.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('candidate.dashboard.programsEmpty')}
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {programs.map((program) => {
                                    const remaining =
                                        program.progress.required_total -
                                        program.progress.required_completed;

                                    return (
                                        <li key={program.id}>
                                            <CardLink
                                                href={learn.show(program.id)}
                                            >
                                                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                                    <span className="font-medium">
                                                        {program.name}
                                                    </span>
                                                    <LearningStatusBadge
                                                        status={
                                                            program.progress
                                                                .status
                                                        }
                                                    />
                                                </div>
                                                <ProgressBar
                                                    percent={
                                                        program.progress.percent
                                                    }
                                                    label={
                                                        remaining > 0
                                                            ? t(
                                                                  'candidate.dashboard.remainingMaterials',
                                                                  {
                                                                      count: remaining,
                                                                  },
                                                              )
                                                            : t(
                                                                  'candidate.dashboard.programDone',
                                                              )
                                                    }
                                                />
                                            </CardLink>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </Section>

                    <Section
                        icon={ExamIcon}
                        title={t('candidate.dashboard.examsTitle')}
                        more={{
                            label: t('candidate.dashboard.allExams'),
                            href: candidateExams.index(),
                        }}
                    >
                        {otherExams.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('candidate.dashboard.examsEmpty')}
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {otherExams.map((exam) => (
                                    <li key={exam.id}>
                                        <CardLink
                                            href={candidateExams.show(exam.id)}
                                        >
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {exam.title}
                                                </span>
                                                <ExamProgressBadge
                                                    progress={exam.progress}
                                                />
                                            </div>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {t(
                                                    `candidate.exams.availability.${exam.availability}`,
                                                )}
                                            </p>
                                            <ClosingCountdown
                                                closesAt={exam.closes_at}
                                                now={now}
                                                className="mt-1 text-xs"
                                            />
                                        </CardLink>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>

                    <Section
                        icon={LearningIcon}
                        title={t('candidate.dashboard.nextTitle')}
                    >
                        {nextMaterials.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {programs.length === 0
                                    ? t('candidate.dashboard.programsEmpty')
                                    : t('candidate.dashboard.nextEmpty')}
                            </p>
                        ) : (
                            <ol className="space-y-2">
                                {nextMaterials.map((material, index) => {
                                    const TypeIcon =
                                        materialTypeIcons[material.type];

                                    return (
                                        <li key={material.id}>
                                            <CardLink
                                                href={learn.material([
                                                    material.program.id,
                                                    material.id,
                                                ])}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <span
                                                        className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold tabular-nums"
                                                        aria-hidden="true"
                                                    >
                                                        {index + 1}
                                                    </span>
                                                    <span className="min-w-0">
                                                        <span className="block truncate text-sm font-medium">
                                                            {material.title}
                                                        </span>
                                                        <span className="flex items-center gap-1 truncate text-xs text-muted-foreground">
                                                            <TypeIcon
                                                                className="size-3.5 shrink-0"
                                                                aria-hidden="true"
                                                            />
                                                            {
                                                                material.program
                                                                    .name
                                                            }
                                                        </span>
                                                    </span>
                                                </div>
                                            </CardLink>
                                        </li>
                                    );
                                })}
                            </ol>
                        )}
                    </Section>

                    <Section
                        icon={ReportIcon}
                        title={t('candidate.dashboard.resultsTitle')}
                    >
                        {results.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('candidate.dashboard.resultsEmpty')}
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {results.map((result) => (
                                    <li key={result.id}>
                                        <CardLink
                                            href={attempts.result(result.id)}
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <div className="min-w-0">
                                                    <span className="block truncate font-medium">
                                                        {result.exam}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground tabular-nums">
                                                        {t(
                                                            'candidate.dashboard.resultLine',
                                                            {
                                                                score:
                                                                    result.score_percent ??
                                                                    '0.00',
                                                                number: result.attempt_number,
                                                            },
                                                        )}
                                                    </span>
                                                </div>
                                                {result.result && (
                                                    <AttemptResultBadge
                                                        result={result.result}
                                                    />
                                                )}
                                            </div>
                                        </CardLink>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>
                </div>
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
