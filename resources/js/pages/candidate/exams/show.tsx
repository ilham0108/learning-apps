import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import {
    AttemptResultBadge,
    AttemptStatusBadge,
    GradingStatusBadge,
} from '@/components/app/exam-badges';
import { PageHeader } from '@/components/app/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { t, tList } from '@/lib/i18n';
import { HistoryIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';
import attempts from '@/routes/attempts';
import candidateProfile from '@/routes/candidate-profile';
import candidateExams from '@/routes/exams';
import learn from '@/routes/learn';
import type {
    AttemptSummary,
    ExamAvailability,
    ExamType,
    NavigationMode,
} from '@/types';

type Props = {
    assignment: {
        id: string;
        title: string;
        description: string | null;
        type: ExamType;
        duration_minutes: number;
        questions_count: number;
        passing_grade: string;
        navigation: NavigationMode;
        opens_at: string | null;
        closes_at: string | null;
        max_attempts: number;
        attempts_used: number;
        attempts_left: number;
        availability: ExamAvailability;
        active_attempt: string | null;
        incomplete_programs: { id: string; name: string }[];
    };
    attempts: AttemptSummary[];
};

export default function CandidateExamShow({
    assignment,
    attempts: history,
}: Props) {
    const { displayTimezone } = usePage().props;
    const [confirmStart, setConfirmStart] = useState(false);
    const [starting, setStarting] = useState(false);
    const canStart = assignment.availability === 'available';
    const inProgress = assignment.availability === 'in_progress';

    // Waktu tutup lebih awal dari durasi penuh: beri tahu sebelum mulai.
    const [closesEarly] = useState(
        () =>
            assignment.closes_at !== null &&
            Date.parse(assignment.closes_at) - Date.now() <
                assignment.duration_minutes * 60_000,
    );

    const facts: [string, string][] = [
        [
            t('candidate.exams.facts.duration'),
            t('candidate.exams.duration', {
                count: assignment.duration_minutes,
            }),
        ],
        [
            t('candidate.exams.facts.questions'),
            t('candidate.exams.questions', {
                count: assignment.questions_count,
            }),
        ],
        [t('candidate.exams.facts.passingGrade'), assignment.passing_grade],
        [
            t('candidate.exams.facts.attempts'),
            t('candidate.exams.attempts', {
                used: assignment.attempts_used,
                max: assignment.max_attempts,
            }),
        ],
        [
            t('candidate.exams.facts.opensAt'),
            formatDateTime(assignment.opens_at, displayTimezone),
        ],
        [
            t('candidate.exams.facts.closesAt'),
            formatDateTime(assignment.closes_at, displayTimezone),
        ],
        [
            t('candidate.exams.facts.navigation'),
            t(`candidate.exams.navigation.${assignment.navigation}`),
        ],
    ];

    return (
        <>
            <Head title={assignment.title} />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={assignment.title}
                    description={t(`status.examType.${assignment.type}`)}
                />

                {assignment.description && (
                    <p className="text-sm whitespace-pre-line">
                        {assignment.description}
                    </p>
                )}

                <Card>
                    <CardContent>
                        <dl className="grid gap-4 text-sm sm:grid-cols-2">
                            {facts.map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="font-medium">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('candidate.exams.rulesTitle')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="list-disc space-y-1.5 pl-5 text-sm">
                            {tList('candidate.exams.rules').map((rule) => (
                                <li key={rule}>{rule}</li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <section
                    className={cn(
                        'space-y-3 rounded-xl border p-4',
                        canStart || inProgress
                            ? 'border-primary/30 bg-primary/5'
                            : 'bg-muted/40',
                    )}
                    aria-live="polite"
                >
                    <p className="text-sm font-medium">
                        {t(
                            `candidate.exams.availability.${assignment.availability}`,
                        )}
                    </p>

                    {assignment.availability === 'not_open' &&
                        assignment.opens_at && (
                            <p className="text-sm text-muted-foreground">
                                {t('candidate.exams.opensAt', {
                                    date: formatDateTime(
                                        assignment.opens_at,
                                        displayTimezone,
                                    ),
                                })}
                            </p>
                        )}

                    {assignment.availability === 'profile_incomplete' && (
                        <Button asChild>
                            <Link href={candidateProfile.edit()}>
                                {t('candidate.exams.completeProfile')}
                            </Link>
                        </Button>
                    )}

                    {assignment.incomplete_programs.length > 0 && (
                        <ul className="list-disc space-y-1 pl-5 text-sm">
                            {assignment.incomplete_programs.map((program) => (
                                <li key={program.id}>
                                    <Link
                                        href={learn.show(program.id)}
                                        className="text-primary hover:underline"
                                    >
                                        {program.name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}

                    {inProgress && assignment.active_attempt && (
                        <Button asChild>
                            <Link
                                href={attempts.show(assignment.active_attempt)}
                            >
                                {t('candidate.exams.continue')}
                            </Link>
                        </Button>
                    )}

                    {canStart && (
                        <div className="flex flex-wrap items-center gap-3">
                            <Button onClick={() => setConfirmStart(true)}>
                                {t('candidate.exams.start')}
                            </Button>
                            <span className="text-sm text-muted-foreground">
                                {t('candidate.exams.attemptsLeft', {
                                    count: assignment.attempts_left,
                                })}
                            </span>
                        </div>
                    )}
                </section>

                {history.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <HistoryIcon
                                className="size-5"
                                aria-hidden="true"
                            />
                            {t('candidate.exams.historyTitle')}
                        </h2>
                        <ul className="divide-y rounded-xl border">
                            {history.map((attempt) => (
                                <li
                                    key={attempt.id}
                                    className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="space-y-1">
                                        <p className="text-sm font-medium">
                                            {t('candidate.exams.attemptLabel', {
                                                number: attempt.attempt_number,
                                            })}
                                        </p>
                                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                            <AttemptStatusBadge
                                                status={attempt.status}
                                            />
                                            {attempt.submitted_at &&
                                                t(
                                                    'candidate.exams.submittedAt',
                                                    {
                                                        date: formatDateTime(
                                                            attempt.submitted_at,
                                                            displayTimezone,
                                                        ),
                                                    },
                                                )}
                                        </div>
                                    </div>
                                    {attempt.status !== 'in_progress' && (
                                        <div className="flex flex-wrap items-center gap-2">
                                            {attempt.score_visible ? (
                                                <>
                                                    <span className="text-sm font-semibold tabular-nums">
                                                        {attempt.score_percent}
                                                    </span>
                                                    {attempt.result && (
                                                        <AttemptResultBadge
                                                            result={
                                                                attempt.result
                                                            }
                                                        />
                                                    )}
                                                </>
                                            ) : attempt.grading_status ===
                                              'pending_manual' ? (
                                                <GradingStatusBadge status="pending_manual" />
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    {t(
                                                        'candidate.exams.scoreHidden',
                                                    )}
                                                </span>
                                            )}
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={attempts.result(
                                                        attempt.id,
                                                    )}
                                                >
                                                    {t(
                                                        'candidate.exams.viewResult',
                                                    )}
                                                </Link>
                                            </Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>

            <ConfirmDialog
                open={confirmStart}
                onOpenChange={setConfirmStart}
                title={t('candidate.exams.startTitle')}
                description={
                    <>
                        {t('candidate.exams.startDescription', {
                            minutes: assignment.duration_minutes,
                        })}
                        {closesEarly && assignment.closes_at && (
                            <>
                                {' '}
                                {t('candidate.exams.startClosesEarly', {
                                    date: formatDateTime(
                                        assignment.closes_at,
                                        displayTimezone,
                                    ),
                                })}
                            </>
                        )}
                    </>
                }
                confirmLabel={t('candidate.exams.start')}
                processing={starting}
                onConfirm={() =>
                    router.post(
                        candidateExams.start(assignment.id).url,
                        {},
                        {
                            onStart: () => setStarting(true),
                            onFinish: () => {
                                setStarting(false);
                                setConfirmStart(false);
                            },
                        },
                    )
                }
            />
        </>
    );
}

CandidateExamShow.layout = {
    breadcrumbs: [
        { title: t('candidate.exams.title'), href: candidateExams.index() },
    ],
};
