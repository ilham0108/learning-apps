import { Head, Link, usePage } from '@inertiajs/react';
import { AttemptStatusBadge } from '@/components/app/exam-badges';
import { PageHeader } from '@/components/app/page-header';
import { ProgressBar } from '@/components/app/progress-bar';
import { AttemptScore } from '@/components/exams/attempt-score';
import { GradeAnswerForm } from '@/components/exams/grade-answer-form';
import { ReviewList } from '@/components/exam-runner/review-list';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import exams from '@/routes/admin/exams';
import examAttempts from '@/routes/admin/exams/attempts';
import type {
    AdminAttemptRow,
    AttemptEventType,
    CategoryScore,
    ReviewItem,
    ReviewQuestion,
} from '@/types';

type Props = {
    exam: { id: string; title: string };
    attempt: AdminAttemptRow & {
        deadline_at: string;
        finalized_at: string | null;
        ip_address: string | null;
        user_agent: string | null;
    };
    categories: CategoryScore[];
    questions: ReviewItem[];
    events: {
        type: AttemptEventType;
        occurred_at: string;
        ip_address: string | null;
    }[];
};

const percent = (earned: string, max: string) =>
    Number(max) > 0 ? Math.round((Number(earned) / Number(max)) * 100) : 0;

const isManual = (question: ReviewQuestion) =>
    question.type === 'essay' || question.type === 'short_answer';

export default function AdminAttemptShow({
    exam,
    attempt,
    categories,
    questions,
    events,
}: Props) {
    const { displayTimezone } = usePage().props;
    const finished = attempt.status !== 'in_progress';
    const final = attempt.grading_status === 'graded';
    const pendingCount = questions
        .flatMap((item) =>
            item.type === 'case_study' ? item.children : [item],
        )
        .filter(
            (question) => question.grading_status === 'pending_manual',
        ).length;

    const facts: [string, string][] = [
        [
            t('admin.attempts.fields.candidateNumber'),
            attempt.candidate.candidate_number,
        ],
        [t('admin.attempts.fields.campaign'), attempt.campaign],
        [
            t('admin.attempts.fields.startedAt'),
            formatDateTime(attempt.started_at, displayTimezone),
        ],
        [
            t('admin.attempts.fields.deadlineAt'),
            formatDateTime(attempt.deadline_at, displayTimezone),
        ],
        [
            t('admin.attempts.fields.submittedAt'),
            formatDateTime(attempt.submitted_at, displayTimezone),
        ],
        [
            t('admin.attempts.fields.finalizedAt'),
            formatDateTime(attempt.finalized_at, displayTimezone),
        ],
        [
            t('admin.attempts.fields.points'),
            t('admin.attempts.pointsValue', {
                earned: attempt.earned_points ?? '0.00',
                max: attempt.max_points ?? '0.00',
            }),
        ],
        [
            t('admin.attempts.fields.ip'),
            attempt.ip_address ?? t('common.notAvailable'),
        ],
    ];

    return (
        <>
            <Head
                title={t('admin.attempts.detailTitle', {
                    name: attempt.candidate.name,
                    number: attempt.attempt_number,
                })}
            />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.attempts.detailTitle', {
                        name: attempt.candidate.name,
                        number: attempt.attempt_number,
                    })}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <Link
                                href={examAttempts.index(exam.id)}
                                className="hover:underline"
                            >
                                {exam.title}
                            </Link>
                            <AttemptStatusBadge status={attempt.status} />
                        </span>
                    }
                />

                {!finished && (
                    <p className="rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">
                        {t('admin.attempts.inProgressNotice')}
                    </p>
                )}
                {finished && pendingCount > 0 && (
                    <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        {t('admin.attempts.pendingNotice', {
                            count: pendingCount,
                        })}
                    </p>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="min-w-0 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('admin.attempts.summaryTitle')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {t('admin.attempts.fields.score')}
                                    </p>
                                    <div className="text-2xl">
                                        <AttemptScore attempt={attempt} />
                                    </div>
                                </div>
                                <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                    {facts.map(([label, value]) => (
                                        <div key={label}>
                                            <dt className="text-muted-foreground">
                                                {label}
                                            </dt>
                                            <dd className="font-medium break-words">
                                                {value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                {categories.length > 0 && (
                                    <div className="space-y-3">
                                        {categories.map((category) => (
                                            <ProgressBar
                                                key={category.name}
                                                percent={percent(
                                                    category.earned_points,
                                                    category.max_points,
                                                )}
                                                label={`${category.name}: ${t(
                                                    'admin.attempts.pointsValue',
                                                    {
                                                        earned: category.earned_points,
                                                        max: category.max_points,
                                                    },
                                                )}`}
                                            />
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <section className="space-y-3">
                            <h2 className="text-lg font-semibold">
                                {t('admin.attempts.questionsTitle')}
                            </h2>
                            <ReviewList
                                items={questions}
                                labels={{
                                    answer: t('admin.attempts.candidateAnswer'),
                                    noAnswer: t('admin.attempts.noAnswer'),
                                    correct: t('admin.attempts.correctOption'),
                                    selected: t(
                                        'admin.attempts.selectedOption',
                                    ),
                                    explanation: t(
                                        'admin.attempts.explanation',
                                    ),
                                }}
                                renderExtra={(question) =>
                                    finished && isManual(question) ? (
                                        <GradeAnswerForm
                                            key={`${question.token}-${question.awarded_points ?? ''}`}
                                            attemptId={attempt.id}
                                            question={question}
                                            final={final}
                                        />
                                    ) : null
                                }
                            />
                        </section>
                    </div>

                    <aside>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('admin.attempts.eventsTitle')}
                                </CardTitle>
                                <p className="text-xs text-muted-foreground">
                                    {t('admin.attempts.eventsHelp')}
                                </p>
                            </CardHeader>
                            <CardContent>
                                {events.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('admin.attempts.eventsEmpty')}
                                    </p>
                                ) : (
                                    <ol className="space-y-3 text-sm">
                                        {events.map((event, index) => (
                                            <li
                                                key={`${event.occurred_at}-${index}`}
                                                className="border-l-2 pl-3"
                                            >
                                                <p className="font-medium">
                                                    {t(
                                                        `status.attemptEvent.${event.type}`,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        event.occurred_at,
                                                        displayTimezone,
                                                    )}
                                                    {event.ip_address &&
                                                        ` · ${event.ip_address}`}
                                                </p>
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>
        </>
    );
}

AdminAttemptShow.layout = {
    breadcrumbs: [{ title: t('admin.exams.title'), href: exams.index() }],
};
