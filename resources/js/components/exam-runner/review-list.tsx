import type { ReactNode } from 'react';
import { StatusBadge } from '@/components/app/status-badge';
import { t } from '@/lib/i18n';
import { CompletedIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';
import type { ReviewItem, ReviewQuestion } from '@/types';

type Labels = {
    answer: string;
    noAnswer: string;
    correct: string;
    selected: string;
    explanation: string;
};

type Props = {
    items: ReviewItem[];
    labels: Labels;
    /** Konten tambahan per soal bernilai, misalnya form penilaian HR. */
    renderExtra?: (question: ReviewQuestion) => ReactNode;
};

function Html({ html, className }: { html: string; className?: string }) {
    return (
        <div
            className={cn(
                'prose prose-sm max-w-none break-words dark:prose-invert',
                className,
            )}
            // Disanitasi di server saat soal disimpan di bank soal.
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}

function Points({ question }: { question: ReviewQuestion }) {
    if (question.awarded_points === null) {
        return (
            <StatusBadge
                label={t('status.answerGrading.pending_manual')}
                tone="warning"
            />
        );
    }

    const full = Number(question.awarded_points) >= Number(question.points);

    return (
        <StatusBadge
            label={t('candidate.result.awarded', {
                earned: question.awarded_points,
                max: question.points,
            })}
            tone={
                full
                    ? 'success'
                    : Number(question.awarded_points) > 0
                      ? 'warning'
                      : 'danger'
            }
        />
    );
}

function QuestionReview({
    question,
    heading,
    labels,
    renderExtra,
}: {
    question: ReviewQuestion;
    heading: string;
    labels: Labels;
    renderExtra?: Props['renderExtra'];
}) {
    const hasOptions = question.options.length > 0;
    const answered =
        question.options.some((option) => option.selected) ||
        (question.text ?? '').trim() !== '';

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-semibold">
                    {heading}
                    <span className="ml-2 font-normal text-muted-foreground">
                        {t(`status.questionType.${question.type}`)} ·{' '}
                        {question.category}
                    </span>
                </h3>
                <Points question={question} />
            </div>

            <Html html={question.body_html} />

            {hasOptions ? (
                <ul className="space-y-1.5">
                    {question.options.map((option) => (
                        <li
                            key={option.token}
                            className={cn(
                                'flex flex-wrap items-start gap-2 rounded-md border px-3 py-2 text-sm',
                                option.is_correct === true &&
                                    'border-green-300 bg-green-50 dark:border-green-900 dark:bg-green-950',
                                option.selected &&
                                    option.is_correct === false &&
                                    'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950',
                            )}
                        >
                            <span className="min-w-0 flex-1 break-words">
                                {option.body}
                            </span>
                            {option.selected && (
                                <StatusBadge
                                    label={labels.selected}
                                    tone="info"
                                />
                            )}
                            {option.is_correct === true && (
                                <span className="inline-flex items-center gap-1 text-xs font-medium text-green-700 dark:text-green-400">
                                    <CompletedIcon
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {labels.correct}
                                </span>
                            )}
                        </li>
                    ))}
                    {!answered && (
                        <li className="text-sm text-muted-foreground">
                            {labels.noAnswer}
                        </li>
                    )}
                </ul>
            ) : (
                <div className="space-y-1">
                    <p className="text-xs font-medium text-muted-foreground">
                        {labels.answer}
                    </p>
                    <p className="rounded-md border bg-muted/40 p-3 text-sm whitespace-pre-wrap">
                        {answered ? question.text : labels.noAnswer}
                    </p>
                </div>
            )}

            {question.explanation_html && (
                <div className="space-y-1 rounded-md border-l-4 border-primary/40 bg-primary/5 p-3">
                    <p className="text-xs font-medium text-muted-foreground">
                        {labels.explanation}
                    </p>
                    <Html html={question.explanation_html} />
                </div>
            )}

            {renderExtra?.(question)}
        </div>
    );
}

/** Daftar soal beserta jawaban, poin, kunci, dan pembahasan (jika dikirim server). */
export function ReviewList({ items, labels, renderExtra }: Props) {
    return (
        <ol className="space-y-4">
            {items.map((item) => (
                <li key={item.token} className="rounded-xl border p-4">
                    {item.type === 'case_study' ? (
                        <div className="space-y-4">
                            <div className="space-y-1">
                                <h3 className="text-sm font-semibold">
                                    {item.position}.{' '}
                                    {t('status.questionType.case_study')}
                                </h3>
                                <Html html={item.body_html} />
                            </div>
                            {item.children.map((child, index) => (
                                <div
                                    key={child.token}
                                    className="border-t pt-4"
                                >
                                    <QuestionReview
                                        question={child}
                                        heading={`${item.position}.${index + 1}`}
                                        labels={labels}
                                        renderExtra={renderExtra}
                                    />
                                </div>
                            ))}
                        </div>
                    ) : (
                        <QuestionReview
                            question={item}
                            heading={`${item.position}.`}
                            labels={labels}
                            renderExtra={renderExtra}
                        />
                    )}
                </li>
            ))}
        </ol>
    );
}
