import { Head, Link, usePage } from '@inertiajs/react';
import { AttemptResultBadge } from '@/components/app/exam-badges';
import { PageHeader } from '@/components/app/page-header';
import { ProgressBar } from '@/components/app/progress-bar';
import { ReviewList } from '@/components/exam-runner/review-list';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { GradingIcon } from '@/lib/icons';
import candidateExams from '@/routes/exams';
import type {
    AttemptSummary,
    CategoryScore,
    ResultRelease,
    ReviewItem,
} from '@/types';

type Props = {
    exam: { title: string; assignment: string; result_release: ResultRelease };
    attempt: AttemptSummary;
    categories: CategoryScore[];
    review: ReviewItem[] | null;
};

const percent = (earned: string, max: string) =>
    Number(max) > 0 ? Math.round((Number(earned) / Number(max)) * 100) : 0;

export default function AttemptResultPage({
    exam,
    attempt,
    categories,
    review,
}: Props) {
    const { displayTimezone } = usePage().props;
    const pending = attempt.grading_status === 'pending_manual';

    return (
        <>
            <Head title={t('candidate.result.title')} />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={exam.title}
                    description={[
                        t('candidate.result.attemptLabel', {
                            number: attempt.attempt_number,
                        }),
                        attempt.submitted_at &&
                            t('candidate.result.submittedAt', {
                                date: formatDateTime(
                                    attempt.submitted_at,
                                    displayTimezone,
                                ),
                            }),
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={candidateExams.show(exam.assignment)}>
                                {t('candidate.result.backToExam')}
                            </Link>
                        </Button>
                    }
                />

                {attempt.score_visible ? (
                    <Card>
                        <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    {t('candidate.result.score')}
                                </p>
                                <p className="text-4xl font-semibold tabular-nums">
                                    {attempt.score_percent}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {t('candidate.result.points', {
                                        earned: attempt.earned_points ?? '0',
                                        max: attempt.max_points ?? '0',
                                    })}{' '}
                                    ·{' '}
                                    {t('candidate.result.passingGrade', {
                                        grade: attempt.passing_grade,
                                    })}
                                </p>
                            </div>
                            {attempt.result && (
                                <AttemptResultBadge result={attempt.result} />
                            )}
                        </CardContent>
                        {attempt.status === 'expired' && (
                            <CardContent className="pt-0 text-sm text-muted-foreground">
                                {t('candidate.result.expiredNote')}
                            </CardContent>
                        )}
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="flex items-start gap-3">
                            <GradingIcon
                                className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div className="space-y-1">
                                <p className="font-medium">
                                    {pending
                                        ? t('candidate.result.pendingTitle')
                                        : t('candidate.result.unreleasedTitle')}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {pending
                                        ? t(
                                              'candidate.result.pendingDescription',
                                          )
                                        : t(
                                              `candidate.result.unreleased.${exam.result_release}`,
                                          )}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {categories.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('candidate.result.categoriesTitle')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {categories.map((category) => (
                                <ProgressBar
                                    key={category.name}
                                    percent={percent(
                                        category.earned_points,
                                        category.max_points,
                                    )}
                                    label={`${category.name}: ${t(
                                        'candidate.result.points',
                                        {
                                            earned: category.earned_points,
                                            max: category.max_points,
                                        },
                                    )}`}
                                />
                            ))}
                        </CardContent>
                    </Card>
                )}

                {review && (
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold">
                            {t('candidate.result.reviewTitle')}
                        </h2>
                        <ReviewList
                            items={review}
                            labels={{
                                answer: t('candidate.result.yourAnswer'),
                                noAnswer: t('candidate.result.noAnswer'),
                                correct: t('candidate.result.correct'),
                                selected: t('candidate.result.yourChoice'),
                                explanation: t('candidate.result.explanation'),
                            }}
                        />
                    </section>
                )}
            </div>
        </>
    );
}

AttemptResultPage.layout = {
    breadcrumbs: [
        { title: t('candidate.exams.title'), href: candidateExams.index() },
    ],
};
