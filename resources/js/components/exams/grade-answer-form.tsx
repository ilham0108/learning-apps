import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/app/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import adminAttempts from '@/routes/admin/attempts';
import type { ReviewQuestion } from '@/types';

type Props = {
    attemptId: string;
    question: ReviewQuestion;
    /** Attempt sudah final: perubahan nilai wajib disertai alasan. */
    final: boolean;
};

/** Penilaian manual satu jawaban esai atau isian singkat. */
export function GradeAnswerForm({ attemptId, question, final }: Props) {
    const { displayTimezone } = usePage().props;
    const form = useForm({
        points: question.awarded_points ?? '',
        note: question.grader_note ?? '',
        reason: '',
    });
    const id = `grade-${question.token}`;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(adminAttempts.grade([attemptId, question.token]), {
            preserveScroll: true,
            onSuccess: () => form.setData('reason', ''),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="space-y-4 rounded-lg border border-dashed p-4"
        >
            {question.rubric && (
                <div className="space-y-1">
                    <p className="text-xs font-medium text-muted-foreground">
                        {t('admin.attempts.rubric')}
                    </p>
                    <p className="text-sm whitespace-pre-line">
                        {question.rubric}
                    </p>
                </div>
            )}
            {question.accepted_answers &&
                question.accepted_answers.length > 0 && (
                    <div className="space-y-1">
                        <p className="text-xs font-medium text-muted-foreground">
                            {t('admin.attempts.acceptedAnswers')}
                        </p>
                        <p className="text-sm">
                            {question.accepted_answers.join(', ')}
                        </p>
                    </div>
                )}

            <div className="grid gap-4 sm:grid-cols-[10rem_minmax(0,1fr)]">
                <FormField
                    id={`${id}-points`}
                    label={t('admin.attempts.grade.points', {
                        max: question.points,
                    })}
                    error={form.errors.points}
                >
                    <Input
                        id={`${id}-points`}
                        type="number"
                        inputMode="decimal"
                        min={0}
                        max={Number(question.points)}
                        step="0.01"
                        required
                        value={form.data.points}
                        onChange={(event) =>
                            form.setData('points', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id={`${id}-note`}
                    label={t('admin.attempts.grade.note')}
                    error={form.errors.note}
                    optional
                >
                    <Textarea
                        id={`${id}-note`}
                        rows={2}
                        maxLength={2000}
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                    />
                </FormField>
            </div>

            {final && (
                <FormField
                    id={`${id}-reason`}
                    label={t('admin.attempts.grade.reason')}
                    description={t('admin.attempts.grade.reasonHelp')}
                    error={form.errors.reason}
                >
                    <Input
                        id={`${id}-reason`}
                        required
                        maxLength={500}
                        value={form.data.reason}
                        aria-describedby={`${id}-reason-description`}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                    />
                </FormField>
            )}

            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-muted-foreground">
                    {question.graded_by &&
                        t('admin.attempts.gradedBy', {
                            name: question.graded_by,
                            date: formatDateTime(
                                question.graded_at ?? null,
                                displayTimezone,
                            ),
                        })}
                </p>
                <Button type="submit" size="sm" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    {t('admin.attempts.grade.save')}
                </Button>
            </div>
        </form>
    );
}
