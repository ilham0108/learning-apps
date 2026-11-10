import { Plus, X } from 'lucide-react';
import { FormField } from '@/components/app/form-field';
import { OptionSelect } from '@/components/app/option-select';
import { RichTextEditor } from '@/components/app/rich-text-editor';
import InputError from '@/components/input-error';
import { difficulties } from '@/components/questions/question-filters';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import type { Difficulty, QuestionType } from '@/types';

export type QuestionItemData = {
    /** ID lokal untuk key React; tidak dikirim ke server. */
    uid: string;
    type: QuestionType;
    difficulty: Difficulty;
    body_html: string;
    explanation_html: string;
    rubric: string;
    default_points: string;
    options: { body: string; is_correct: boolean }[];
    accepted_answers: string[];
};

let counter = 0;

export function newUid(): string {
    counter += 1;

    return `item-${counter}`;
}

export function emptyItem(
    type: QuestionType = 'multiple_choice',
): QuestionItemData {
    return {
        uid: newUid(),
        type,
        difficulty: 'medium',
        body_html: '',
        explanation_html: '',
        rubric: '',
        default_points: '10',
        options: defaultOptions(type),
        accepted_answers: type === 'short_answer' ? [''] : [],
    };
}

export function defaultOptions(
    type: QuestionType,
): QuestionItemData['options'] {
    if (type === 'true_false') {
        return [
            { body: t('admin.questions.fields.true'), is_correct: true },
            { body: t('admin.questions.fields.false'), is_correct: false },
        ];
    }

    if (type === 'multiple_choice' || type === 'multiple_select') {
        return [0, 1, 2, 3].map((index) => ({
            body: '',
            is_correct: index === 0,
        }));
    }

    return [];
}

type Props = {
    item: QuestionItemData;
    onChange: (item: QuestionItemData) => void;
    errors: Record<string, string | undefined>;
    /** Awalan kunci error, misalnya `children.0.`. */
    errorPrefix?: string;
    idPrefix: string;
    showPoints?: boolean;
    showDifficulty?: boolean;
    bodyLabel?: string;
};

/**
 * Editor isi satu soal: pertanyaan, jawaban sesuai jenis, bobot, rubrik, dan pembahasan.
 */
export function QuestionItemEditor({
    item,
    onChange,
    errors,
    errorPrefix = '',
    idPrefix,
    showPoints = true,
    showDifficulty = false,
    bodyLabel = t('admin.questions.fields.body'),
}: Props) {
    const set = <K extends keyof QuestionItemData>(
        key: K,
        value: QuestionItemData[K],
    ) => onChange({ ...item, [key]: value });
    const error = (key: string) => errors[`${errorPrefix}${key}`];

    return (
        <div className="grid gap-6">
            <FormField
                id={`${idPrefix}-body`}
                label={bodyLabel}
                error={error('body_html')}
            >
                <RichTextEditor
                    id={`${idPrefix}-body`}
                    value={item.body_html}
                    onChange={(html) => set('body_html', html)}
                    invalid={error('body_html') !== undefined}
                    ariaLabel={bodyLabel}
                />
            </FormField>

            {(item.type === 'multiple_choice' ||
                item.type === 'multiple_select') && (
                <OptionsEditor
                    item={item}
                    onChange={onChange}
                    error={error('options')}
                    idPrefix={idPrefix}
                />
            )}

            {item.type === 'true_false' && (
                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">
                        {t('admin.questions.fields.correctAnswer')}
                    </legend>
                    <div className="flex gap-6">
                        {[true, false].map((value) => (
                            <label
                                key={String(value)}
                                className="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="radio"
                                    name={`${idPrefix}-tf`}
                                    checked={
                                        item.options[0]?.is_correct === value
                                    }
                                    onChange={() =>
                                        set('options', [
                                            {
                                                body: t(
                                                    'admin.questions.fields.true',
                                                ),
                                                is_correct: value,
                                            },
                                            {
                                                body: t(
                                                    'admin.questions.fields.false',
                                                ),
                                                is_correct: !value,
                                            },
                                        ])
                                    }
                                    className="size-4 accent-primary"
                                />
                                {value
                                    ? t('admin.questions.fields.true')
                                    : t('admin.questions.fields.false')}
                            </label>
                        ))}
                    </div>
                </fieldset>
            )}

            {item.type === 'short_answer' && (
                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">
                        {t('admin.questions.fields.acceptedAnswers')}
                    </legend>
                    <p className="text-sm text-muted-foreground">
                        {t('admin.questions.fields.acceptedAnswersHelp')}
                    </p>
                    {item.accepted_answers.map((answer, index) => (
                        <div key={index} className="flex gap-2">
                            <Input
                                value={answer}
                                aria-label={t(
                                    'admin.questions.fields.acceptedPlaceholder',
                                    { number: index + 1 },
                                )}
                                placeholder={t(
                                    'admin.questions.fields.acceptedPlaceholder',
                                    { number: index + 1 },
                                )}
                                onChange={(event) =>
                                    set(
                                        'accepted_answers',
                                        item.accepted_answers.map((value, i) =>
                                            i === index
                                                ? event.target.value
                                                : value,
                                        ),
                                    )
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t(
                                    'admin.questions.fields.removeAccepted',
                                    { number: index + 1 },
                                )}
                                title={t(
                                    'admin.questions.fields.removeAccepted',
                                    { number: index + 1 },
                                )}
                                disabled={item.accepted_answers.length <= 1}
                                onClick={() =>
                                    set(
                                        'accepted_answers',
                                        item.accepted_answers.filter(
                                            (_, i) => i !== index,
                                        ),
                                    )
                                }
                            >
                                <X aria-hidden="true" />
                            </Button>
                        </div>
                    ))}
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={item.accepted_answers.length >= 20}
                            onClick={() =>
                                set('accepted_answers', [
                                    ...item.accepted_answers,
                                    '',
                                ])
                            }
                        >
                            <Plus aria-hidden="true" />
                            {t('admin.questions.fields.addAccepted')}
                        </Button>
                    </div>
                    <InputError message={error('accepted_answers')} />
                </fieldset>
            )}

            {item.type === 'essay' && (
                <FormField
                    id={`${idPrefix}-rubric`}
                    label={t('admin.questions.fields.rubric')}
                    error={error('rubric')}
                    description={t('admin.questions.fields.rubricHelp')}
                    optional
                >
                    <Textarea
                        id={`${idPrefix}-rubric`}
                        value={item.rubric}
                        onChange={(event) => set('rubric', event.target.value)}
                        maxLength={5000}
                    />
                </FormField>
            )}

            {(showPoints || showDifficulty) && (
                <div className="grid gap-6 sm:grid-cols-2">
                    {showPoints && (
                        <FormField
                            id={`${idPrefix}-points`}
                            label={t('admin.questions.fields.points')}
                            error={error('default_points')}
                        >
                            <Input
                                id={`${idPrefix}-points`}
                                type="number"
                                min="0.01"
                                step="0.01"
                                inputMode="decimal"
                                value={item.default_points}
                                onChange={(event) =>
                                    set('default_points', event.target.value)
                                }
                                required
                            />
                        </FormField>
                    )}
                    {showDifficulty && (
                        <FormField
                            id={`${idPrefix}-difficulty`}
                            label={t('admin.questions.fields.difficulty')}
                            error={error('difficulty')}
                        >
                            <OptionSelect
                                id={`${idPrefix}-difficulty`}
                                value={item.difficulty}
                                onChange={(value) =>
                                    set(
                                        'difficulty',
                                        (value ?? 'medium') as Difficulty,
                                    )
                                }
                                options={difficulties.map((difficulty) => ({
                                    value: difficulty,
                                    label: t(`status.difficulty.${difficulty}`),
                                }))}
                            />
                        </FormField>
                    )}
                </div>
            )}

            {item.type !== 'case_study' && (
                <FormField
                    id={`${idPrefix}-explanation`}
                    label={t('admin.questions.fields.explanation')}
                    error={error('explanation_html')}
                    description={t('admin.questions.fields.explanationHelp')}
                    optional
                >
                    <RichTextEditor
                        id={`${idPrefix}-explanation`}
                        value={item.explanation_html}
                        onChange={(html) => set('explanation_html', html)}
                        ariaLabel={t('admin.questions.fields.explanation')}
                    />
                </FormField>
            )}
        </div>
    );
}

function OptionsEditor({
    item,
    onChange,
    error,
    idPrefix,
}: {
    item: QuestionItemData;
    onChange: (item: QuestionItemData) => void;
    error: string | undefined;
    idPrefix: string;
}) {
    const single = item.type === 'multiple_choice';
    const setOptions = (options: QuestionItemData['options']) =>
        onChange({ ...item, options });

    const toggleCorrect = (index: number, checked: boolean) =>
        setOptions(
            item.options.map((option, i) => ({
                ...option,
                is_correct: single
                    ? i === index
                    : i === index
                      ? checked
                      : option.is_correct,
            })),
        );

    return (
        <fieldset className="grid gap-2">
            <legend className="text-sm font-medium">
                {t('admin.questions.fields.options')}
            </legend>
            <p className="text-sm text-muted-foreground">
                {single
                    ? t('admin.questions.fields.optionsSingleHelp')
                    : t('admin.questions.fields.optionsHelp')}
            </p>
            {item.options.map((option, index) => {
                const correctLabel = t('admin.questions.fields.optionCorrect', {
                    number: index + 1,
                });

                return (
                    <div key={index} className="flex items-center gap-2">
                        {single ? (
                            <input
                                type="radio"
                                name={`${idPrefix}-correct`}
                                checked={option.is_correct}
                                onChange={() => toggleCorrect(index, true)}
                                aria-label={correctLabel}
                                className="size-4 shrink-0 accent-primary"
                            />
                        ) : (
                            <Checkbox
                                checked={option.is_correct}
                                onCheckedChange={(checked) =>
                                    toggleCorrect(index, checked === true)
                                }
                                aria-label={correctLabel}
                            />
                        )}
                        <Label
                            htmlFor={`${idPrefix}-option-${index}`}
                            className="sr-only"
                        >
                            {t('admin.questions.fields.optionPlaceholder', {
                                number: index + 1,
                            })}
                        </Label>
                        <Input
                            id={`${idPrefix}-option-${index}`}
                            value={option.body}
                            placeholder={t(
                                'admin.questions.fields.optionPlaceholder',
                                { number: index + 1 },
                            )}
                            maxLength={1000}
                            onChange={(event) =>
                                setOptions(
                                    item.options.map((value, i) =>
                                        i === index
                                            ? {
                                                  ...value,
                                                  body: event.target.value,
                                              }
                                            : value,
                                    ),
                                )
                            }
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={t(
                                'admin.questions.fields.removeOption',
                                { number: index + 1 },
                            )}
                            title={t('admin.questions.fields.removeOption', {
                                number: index + 1,
                            })}
                            disabled={item.options.length <= 2}
                            onClick={() =>
                                setOptions(
                                    item.options.filter((_, i) => i !== index),
                                )
                            }
                        >
                            <X aria-hidden="true" />
                        </Button>
                    </div>
                );
            })}
            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={item.options.length >= 10}
                    onClick={() =>
                        setOptions([
                            ...item.options,
                            { body: '', is_correct: false },
                        ])
                    }
                >
                    <Plus aria-hidden="true" />
                    {t('admin.questions.fields.addOption')}
                </Button>
            </div>
            <InputError message={error} />
        </fieldset>
    );
}
