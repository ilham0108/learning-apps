import { useId } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { FlagIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';
import type { RunnerAnswer, RunnerItem, RunnerQuestion } from '@/types';

export type AnswerChange = Partial<
    Pick<RunnerAnswer, 'selected' | 'text' | 'flagged'>
>;

type Props = {
    item: RunnerItem;
    answers: Record<string, RunnerAnswer>;
    onChange: (token: string, change: AnswerChange) => void;
    disabled: boolean;
};

/** Isi soal disanitasi di server (HtmlSanitizer) saat disimpan di bank soal. */
function QuestionBody({ html }: { html: string }) {
    return (
        <div
            className="prose prose-sm max-w-none break-words dark:prose-invert"
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}

function FlagButton({
    flagged,
    onToggle,
    disabled,
}: {
    flagged: boolean;
    onToggle: () => void;
    disabled: boolean;
}) {
    return (
        <Button
            type="button"
            variant={flagged ? 'secondary' : 'outline'}
            size="sm"
            onClick={onToggle}
            disabled={disabled}
            aria-pressed={flagged}
            className={cn(
                flagged &&
                    'border-amber-300 bg-amber-100 text-amber-900 hover:bg-amber-200 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
            )}
        >
            <FlagIcon aria-hidden="true" />
            {flagged
                ? t('candidate.runner.unflag')
                : t('candidate.runner.flag')}
        </Button>
    );
}

function AnswerInput({
    question,
    answer,
    label,
    onChange,
    disabled,
}: {
    question: RunnerQuestion;
    answer: RunnerAnswer | undefined;
    label: string;
    onChange: (change: AnswerChange) => void;
    disabled: boolean;
}) {
    const inputId = useId();
    const selected = answer?.selected ?? [];

    if (question.type === 'short_answer') {
        return (
            <Input
                id={inputId}
                aria-label={label}
                value={answer?.text ?? ''}
                onChange={(event) => onChange({ text: event.target.value })}
                disabled={disabled}
                maxLength={255}
                autoComplete="off"
                className="max-w-md"
            />
        );
    }

    if (question.type === 'essay') {
        const length = answer?.text?.length ?? 0;

        return (
            <div className="space-y-1">
                <Textarea
                    id={inputId}
                    aria-label={label}
                    aria-describedby={`${inputId}-count`}
                    value={answer?.text ?? ''}
                    onChange={(event) => onChange({ text: event.target.value })}
                    disabled={disabled}
                    maxLength={20000}
                    rows={8}
                    placeholder={t('candidate.runner.essayPlaceholder')}
                />
                <p
                    id={`${inputId}-count`}
                    className="text-right text-xs text-muted-foreground tabular-nums"
                >
                    {t('candidate.runner.characters', { count: length })}
                </p>
            </div>
        );
    }

    const multiple = question.type === 'multiple_select';

    return (
        <fieldset className="space-y-2" disabled={disabled}>
            <legend className="sr-only">{label}</legend>
            {question.options.map((option, index) => {
                const checked = selected.includes(option.token);

                return (
                    <label
                        key={option.token}
                        className={cn(
                            'flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                            checked
                                ? 'border-primary bg-primary/5'
                                : 'hover:bg-muted/50',
                            disabled && 'cursor-not-allowed opacity-70',
                        )}
                    >
                        <input
                            type={multiple ? 'checkbox' : 'radio'}
                            name={`${inputId}-options`}
                            value={option.token}
                            checked={checked}
                            onChange={() =>
                                onChange({
                                    selected: multiple
                                        ? checked
                                            ? selected.filter(
                                                  (token) =>
                                                      token !== option.token,
                                              )
                                            : [...selected, option.token]
                                        : [option.token],
                                })
                            }
                            className="mt-0.5 size-4 shrink-0 accent-primary"
                        />
                        <span className="flex-1 break-words">
                            {question.type !== 'true_false' && (
                                <span className="mr-1.5 font-medium text-muted-foreground">
                                    {String.fromCharCode(65 + index)}.
                                </span>
                            )}
                            {option.body}
                        </span>
                    </label>
                );
            })}
        </fieldset>
    );
}

function QuestionBlock({
    question,
    heading,
    answers,
    onChange,
    disabled,
}: {
    question: RunnerQuestion;
    heading: string;
    answers: Record<string, RunnerAnswer>;
    onChange: Props['onChange'];
    disabled: boolean;
}) {
    const answer = answers[question.token];

    return (
        <section
            className="space-y-4"
            aria-label={t('candidate.runner.navigatorItem', {
                number: heading,
            })}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground">
                    {t(`candidate.runner.hint.${question.type}`)} ·{' '}
                    {t('candidate.runner.points', { points: question.points })}
                </p>
                <FlagButton
                    flagged={answer?.flagged ?? false}
                    onToggle={() =>
                        onChange(question.token, {
                            flagged: !(answer?.flagged ?? false),
                        })
                    }
                    disabled={disabled}
                />
            </div>
            <QuestionBody html={question.body_html} />
            <AnswerInput
                question={question}
                answer={answer}
                label={t('candidate.runner.answerLabel', { number: heading })}
                onChange={(change) => onChange(question.token, change)}
                disabled={disabled}
            />
        </section>
    );
}

/** Satu nomor soal. Studi kasus menampilkan teks kasus lalu semua sub-soalnya. */
export function QuestionView({ item, answers, onChange, disabled }: Props) {
    if (item.type !== 'case_study') {
        return (
            <QuestionBlock
                question={item}
                heading={String(item.position)}
                answers={answers}
                onChange={onChange}
                disabled={disabled}
            />
        );
    }

    return (
        <div className="space-y-6">
            <div className="space-y-2 rounded-lg border bg-muted/40 p-4">
                <p className="text-sm text-muted-foreground">
                    {t('candidate.runner.hint.case_study')}
                </p>
                <QuestionBody html={item.body_html} />
            </div>
            {item.children.map((child, index) => (
                <div key={child.token} className="space-y-2 border-t pt-6">
                    <h3 className="text-sm font-semibold">
                        {t('candidate.runner.subQuestion', {
                            number: `${item.position}.${index + 1}`,
                        })}
                    </h3>
                    <QuestionBlock
                        question={child}
                        heading={`${item.position}.${index + 1}`}
                        answers={answers}
                        onChange={onChange}
                        disabled={disabled}
                    />
                </div>
            ))}
        </div>
    );
}

/** Soal dianggap dijawab jika ada pilihan atau teks; studi kasus jika semua sub-soal dijawab. */
export function isAnswered(
    item: RunnerItem,
    answers: Record<string, RunnerAnswer>,
): boolean {
    const answered = (question: RunnerQuestion) => {
        const answer = answers[question.token];

        return (
            (answer?.selected.length ?? 0) > 0 ||
            (answer?.text ?? '').trim() !== ''
        );
    };

    return item.type === 'case_study'
        ? item.children.length > 0 && item.children.every(answered)
        : answered(item);
}

export function isFlagged(
    item: RunnerItem,
    answers: Record<string, RunnerAnswer>,
): boolean {
    const questions = item.type === 'case_study' ? item.children : [item];

    return questions.some((question) => answers[question.token]?.flagged);
}
