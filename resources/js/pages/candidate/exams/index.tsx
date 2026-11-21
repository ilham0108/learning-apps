import { Head, Link, usePage } from '@inertiajs/react';
import { EmptyState } from '@/components/app/empty-state';
import { ExamProgressBadge } from '@/components/app/exam-badges';
import { PageHeader } from '@/components/app/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { CalendarIcon, ExamIcon, TimerIcon } from '@/lib/icons';
import candidateExams from '@/routes/exams';
import type {
    AttemptSummary,
    ExamAvailability,
    ExamProgress,
    ExamType,
} from '@/types';

type ExamRow = {
    id: string;
    title: string;
    type: ExamType;
    campaign: string;
    duration_minutes: number;
    opens_at: string | null;
    closes_at: string | null;
    availability: ExamAvailability;
    progress: ExamProgress;
    attempts_used: number;
    max_attempts: number;
    latest: AttemptSummary | null;
};

type Props = { exams: ExamRow[] };

export default function CandidateExamsIndex({ exams }: Props) {
    const { displayTimezone } = usePage().props;

    return (
        <>
            <Head title={t('candidate.exams.title')} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('candidate.exams.title')}
                    description={t('candidate.exams.description')}
                />

                {exams.length === 0 ? (
                    <EmptyState
                        icon={ExamIcon}
                        title={t('candidate.exams.empty')}
                        description={t('candidate.exams.emptyDescription')}
                    />
                ) : (
                    <ul className="grid gap-3">
                        {exams.map((exam) => (
                            <li key={exam.id}>
                                <Card>
                                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="min-w-0 space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="font-medium">
                                                    {exam.title}
                                                </h2>
                                                <ExamProgressBadge
                                                    progress={exam.progress}
                                                />
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {t(
                                                    `status.examType.${exam.type}`,
                                                )}{' '}
                                                · {exam.campaign}
                                            </p>
                                            <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                                <li className="flex items-center gap-1">
                                                    <TimerIcon
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    {t(
                                                        'candidate.exams.duration',
                                                        {
                                                            count: exam.duration_minutes,
                                                        },
                                                    )}
                                                </li>
                                                <li className="flex items-center gap-1">
                                                    <CalendarIcon
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    {exam.closes_at
                                                        ? t(
                                                              'candidate.exams.closesAt',
                                                              {
                                                                  date: formatDateTime(
                                                                      exam.closes_at,
                                                                      displayTimezone,
                                                                  ),
                                                              },
                                                          )
                                                        : t(
                                                              'candidate.exams.noSchedule',
                                                          )}
                                                </li>
                                                <li>
                                                    {t(
                                                        'candidate.exams.attempts',
                                                        {
                                                            used: exam.attempts_used,
                                                            max: exam.max_attempts,
                                                        },
                                                    )}
                                                </li>
                                                {exam.latest?.score_visible && (
                                                    <li className="font-medium text-foreground">
                                                        {t(
                                                            'candidate.exams.latestScore',
                                                            {
                                                                score:
                                                                    exam.latest
                                                                        .score_percent ??
                                                                    '0.00',
                                                            },
                                                        )}
                                                    </li>
                                                )}
                                            </ul>
                                        </div>
                                        <Button
                                            asChild
                                            variant={
                                                exam.availability ===
                                                    'available' ||
                                                exam.availability ===
                                                    'in_progress'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className="shrink-0"
                                        >
                                            <Link
                                                href={candidateExams.show(
                                                    exam.id,
                                                )}
                                            >
                                                {exam.availability ===
                                                'in_progress'
                                                    ? t(
                                                          'candidate.exams.continue',
                                                      )
                                                    : t('candidate.exams.open')}
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

CandidateExamsIndex.layout = {
    breadcrumbs: [
        { title: t('candidate.exams.title'), href: candidateExams.index() },
    ],
};
