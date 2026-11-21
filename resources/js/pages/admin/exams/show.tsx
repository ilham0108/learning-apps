import { Head, Link, router, usePage } from '@inertiajs/react';
import { Copy, ListChecks, Megaphone, Pencil } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { ExamStatusBadge, LockedBadge } from '@/components/app/exam-badges';
import { PageHeader } from '@/components/app/page-header';
import { ExamAssignments } from '@/components/exams/exam-assignments';
import { ExamQuestions } from '@/components/exams/exam-questions';
import type { AssignOptions } from '@/components/programs/assignment-panel';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import exams from '@/routes/admin/exams';
import examAttempts from '@/routes/admin/exams/attempts';
import type {
    CategoryOption,
    ExamAssignmentRow,
    ExamDetail,
    ExamQuestionRow,
} from '@/types';

type Props = {
    exam: ExamDetail;
    questions: ExamQuestionRow[];
    assignments: ExamAssignmentRow[];
    assignOptions: AssignOptions;
    categories: CategoryOption[];
    attemptStats: { total: number; pending_grading: number };
};

export default function ExamShow({
    exam,
    questions,
    assignments,
    assignOptions,
    categories,
    attemptStats,
}: Props) {
    const { displayTimezone } = usePage().props;
    const [confirmDuplicate, setConfirmDuplicate] = useState(false);
    const [duplicating, setDuplicating] = useState(false);
    const [confirmRelease, setConfirmRelease] = useState(false);
    const [releasing, setReleasing] = useState(false);

    const facts: [string, string][] = [
        [t('admin.exams.fields.type'), t(`status.examType.${exam.type}`)],
        [
            t('admin.exams.fields.duration'),
            t('admin.exams.summary.duration', { count: exam.duration_minutes }),
        ],
        [
            t('admin.exams.fields.passingGrade'),
            t('admin.exams.summary.passingGrade', {
                grade: exam.passing_grade,
            }),
        ],
        [
            t('admin.exams.fields.maxAttempts'),
            t('admin.exams.summary.attempts', { count: exam.max_attempts }),
        ],
        [
            t('admin.exams.fields.opensAt'),
            exam.opens_at
                ? formatDateTime(exam.opens_at, displayTimezone)
                : t('common.notAvailable'),
        ],
        [
            t('admin.exams.fields.closesAt'),
            exam.closes_at
                ? formatDateTime(exam.closes_at, displayTimezone)
                : t('common.notAvailable'),
        ],
        [
            t('admin.exams.fields.navigation'),
            t(`status.navigation.${exam.navigation}`),
        ],
        [
            t('admin.exams.fields.scorePolicy'),
            t(`status.scorePolicy.${exam.score_policy}`),
        ],
        [
            t('admin.exams.fields.multiSelectScoring'),
            t(`status.multiSelectScoring.${exam.multi_select_scoring}`),
        ],
        [
            t('admin.exams.fields.resultRelease'),
            t(`status.resultRelease.${exam.result_release}`),
        ],
        [
            t('admin.exams.fields.showReview'),
            exam.show_review ? t('common.yes') : t('common.no'),
        ],
        [
            t('admin.exams.fields.prerequisites'),
            exam.prerequisite_programs
                ?.map((program) => program.name)
                .join(', ') || t('common.notAvailable'),
        ],
    ];

    return (
        <>
            <Head title={exam.title} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={exam.title}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <ExamStatusBadge status={exam.status} />
                            {exam.is_locked && <LockedBadge />}
                            {exam.duplicated_from && (
                                <Link
                                    href={exams.show(exam.duplicated_from.id)}
                                    className="hover:underline"
                                >
                                    {t('admin.exams.duplicatedFrom', {
                                        title: exam.duplicated_from.title,
                                    })}
                                </Link>
                            )}
                        </span>
                    }
                    actions={
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setConfirmDuplicate(true)}
                            >
                                <Copy aria-hidden="true" />
                                {t('admin.exams.duplicate')}
                            </Button>
                            <Button asChild>
                                <Link href={exams.edit(exam.id)}>
                                    <Pencil aria-hidden="true" />
                                    {t('common.edit')}
                                </Link>
                            </Button>
                        </>
                    }
                />

                {exam.is_locked && (
                    <p className="rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">
                        {t('admin.exams.lockedNotice')}
                    </p>
                )}

                {exam.description && (
                    <p className="max-w-3xl text-sm whitespace-pre-line">
                        {exam.description}
                    </p>
                )}

                <Card>
                    <CardContent>
                        <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
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
                    <CardContent className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="space-y-1">
                            <h2 className="font-semibold">
                                {t('admin.exams.attemptStats.title')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t('admin.exams.attemptStats.summary', {
                                    total: attemptStats.total,
                                    pending: attemptStats.pending_grading,
                                })}
                            </p>
                            {exam.results_released_at && (
                                <p className="text-sm text-muted-foreground">
                                    {t('admin.exams.release.releasedAt', {
                                        date: formatDateTime(
                                            exam.results_released_at,
                                            displayTimezone,
                                        ),
                                    })}
                                </p>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {exam.result_release !== 'immediate' &&
                                !exam.results_released_at && (
                                    <Button
                                        variant="outline"
                                        onClick={() => setConfirmRelease(true)}
                                    >
                                        <Megaphone aria-hidden="true" />
                                        {t('admin.exams.release.action')}
                                    </Button>
                                )}
                            <Button asChild variant="outline">
                                <Link href={examAttempts.index(exam.id)}>
                                    <ListChecks aria-hidden="true" />
                                    {t('admin.exams.attemptStats.view')}
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <ExamQuestions
                    exam={exam}
                    rows={questions}
                    categories={categories}
                />
                <Separator />
                <ExamAssignments
                    exam={exam}
                    rows={assignments}
                    options={assignOptions}
                />
            </div>

            <ConfirmDialog
                open={confirmRelease}
                onOpenChange={setConfirmRelease}
                title={t('admin.exams.release.title')}
                description={t('admin.exams.release.description')}
                confirmLabel={t('admin.exams.release.action')}
                processing={releasing}
                onConfirm={() =>
                    router.visit(exams.releaseResults(exam.id), {
                        preserveScroll: true,
                        onStart: () => setReleasing(true),
                        onFinish: () => {
                            setReleasing(false);
                            setConfirmRelease(false);
                        },
                    })
                }
            />

            <ConfirmDialog
                open={confirmDuplicate}
                onOpenChange={setConfirmDuplicate}
                title={t('admin.exams.duplicateTitle')}
                description={t('admin.exams.duplicateDescription')}
                confirmLabel={t('admin.exams.duplicate')}
                processing={duplicating}
                onConfirm={() =>
                    router.visit(exams.duplicate(exam.id), {
                        onStart: () => setDuplicating(true),
                        onFinish: () => {
                            setDuplicating(false);
                            setConfirmDuplicate(false);
                        },
                    })
                }
            />
        </>
    );
}

ExamShow.layout = {
    breadcrumbs: [{ title: t('admin.exams.title'), href: exams.index() }],
};
