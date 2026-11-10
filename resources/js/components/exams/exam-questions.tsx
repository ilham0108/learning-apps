import { Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { StatusBadge } from '@/components/app/status-badge';
import { QuestionPickerDialog } from '@/components/exams/question-picker-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import exams from '@/routes/admin/exams';
import questions from '@/routes/admin/questions';
import type { CategoryOption, ExamDetail, ExamQuestionRow } from '@/types';

export function ExamQuestions({
    exam,
    rows,
    categories,
}: {
    exam: ExamDetail;
    rows: ExamQuestionRow[];
    categories: CategoryOption[];
}) {
    const [pickerOpen, setPickerOpen] = useState(false);
    const editable = !exam.is_locked;
    const total = rows.reduce((sum, row) => sum + Number(row.points), 0);

    const visit = (
        route: Parameters<typeof router.visit>[0],
        data?: Record<string, string>,
    ) => router.visit(route, { data, preserveScroll: true });

    return (
        <section className="space-y-4" aria-labelledby="exam-questions-title">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h2
                        id="exam-questions-title"
                        className="text-lg font-semibold"
                    >
                        {t('admin.exams.questions.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('admin.exams.questions.total', {
                            points: total.toFixed(2),
                        })}
                    </p>
                </div>
                {editable && (
                    <Button onClick={() => setPickerOpen(true)}>
                        <Plus aria-hidden="true" />
                        {t('admin.exams.questions.add')}
                    </Button>
                )}
            </div>

            {rows.length === 0 ? (
                <EmptyState title={t('admin.exams.questions.empty')} />
            ) : (
                <ol className="divide-y rounded-xl border">
                    {rows.map((row, index) => (
                        <li
                            key={row.id}
                            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center"
                        >
                            <span className="w-6 shrink-0 text-sm font-medium text-muted-foreground tabular-nums">
                                {index + 1}.
                            </span>
                            <div className="min-w-0 flex-1">
                                <Link
                                    href={questions.edit(row.id)}
                                    className="text-sm font-medium hover:underline"
                                >
                                    {row.excerpt}
                                </Link>
                                <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                    <span>
                                        {[
                                            t(
                                                `status.questionType.${row.type}`,
                                            ),
                                            row.category,
                                            t(
                                                `status.difficulty.${row.difficulty}`,
                                            ),
                                        ].join(' · ')}
                                    </span>
                                    {row.is_superseded && (
                                        <StatusBadge
                                            label={t(
                                                'admin.exams.questions.outdated',
                                            )}
                                            tone="warning"
                                        />
                                    )}
                                </div>
                            </div>
                            <div className="flex items-center gap-1">
                                {row.type === 'case_study' || !editable ? (
                                    <span
                                        className="w-24 text-right text-sm tabular-nums"
                                        title={
                                            row.type === 'case_study'
                                                ? t(
                                                      'admin.exams.questions.caseStudyPoints',
                                                  )
                                                : undefined
                                        }
                                    >
                                        {row.points}
                                    </span>
                                ) : (
                                    <Input
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        inputMode="decimal"
                                        defaultValue={row.points}
                                        aria-label={t(
                                            'admin.exams.questions.pointsFor',
                                            { number: index + 1 },
                                        )}
                                        className="w-24 text-right"
                                        onBlur={(event) => {
                                            if (
                                                event.target.value !==
                                                row.points
                                            ) {
                                                visit(
                                                    exams.questions.update([
                                                        exam.id,
                                                        row.id,
                                                    ]),
                                                    {
                                                        points: event.target
                                                            .value,
                                                    },
                                                );
                                            }
                                        }}
                                    />
                                )}
                                {editable && (
                                    <>
                                        <IconButton
                                            label={t(
                                                'admin.programs.content.moveUp',
                                            )}
                                            disabled={index === 0}
                                            onClick={() =>
                                                visit(
                                                    exams.questions.move([
                                                        exam.id,
                                                        row.id,
                                                    ]),
                                                    { direction: 'up' },
                                                )
                                            }
                                        >
                                            <ArrowUp aria-hidden="true" />
                                        </IconButton>
                                        <IconButton
                                            label={t(
                                                'admin.programs.content.moveDown',
                                            )}
                                            disabled={index === rows.length - 1}
                                            onClick={() =>
                                                visit(
                                                    exams.questions.move([
                                                        exam.id,
                                                        row.id,
                                                    ]),
                                                    { direction: 'down' },
                                                )
                                            }
                                        >
                                            <ArrowDown aria-hidden="true" />
                                        </IconButton>
                                        <IconButton
                                            label={t(
                                                'admin.exams.questions.remove',
                                            )}
                                            onClick={() =>
                                                visit(
                                                    exams.questions.destroy([
                                                        exam.id,
                                                        row.id,
                                                    ]),
                                                )
                                            }
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </IconButton>
                                    </>
                                )}
                            </div>
                        </li>
                    ))}
                </ol>
            )}

            {pickerOpen && (
                <QuestionPickerDialog
                    examId={exam.id}
                    categories={categories}
                    onClose={() => setPickerOpen(false)}
                />
            )}
        </section>
    );
}

function IconButton({
    label,
    onClick,
    disabled = false,
    children,
}: {
    label: string;
    onClick: () => void;
    disabled?: boolean;
    children: React.ReactNode;
}) {
    return (
        <Button
            variant="ghost"
            size="icon"
            className="size-8"
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}
