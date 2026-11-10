import { Head, Link, router, useForm } from '@inertiajs/react';
import { Archive, Plus, RotateCcw, Trash2, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import {
    ExamStatusBadge,
    LockedBadge,
    QuestionStatusBadge,
} from '@/components/app/exam-badges';
import { FormField } from '@/components/app/form-field';
import { OptionSelect } from '@/components/app/option-select';
import { PageHeader } from '@/components/app/page-header';
import InputError from '@/components/input-error';
import {
    difficulties,
    questionTypes,
} from '@/components/questions/question-filters';
import {
    defaultOptions,
    emptyItem,
    newUid,
    QuestionItemEditor,
} from '@/components/questions/question-item-editor';
import type { QuestionItemData } from '@/components/questions/question-item-editor';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import exams from '@/routes/admin/exams';
import questions from '@/routes/admin/questions';
import type {
    CategoryOption,
    Difficulty,
    ExamStatus,
    QuestionContent,
    QuestionDetail,
    QuestionType,
} from '@/types';

type Usage = {
    exam: { id: string; title: string; status: ExamStatus; is_locked: boolean };
    version: number;
}[];

type Props = {
    question: QuestionDetail | null;
    categories: CategoryOption[];
    usage: Usage;
    lockedUsage: boolean;
};

type FormData = QuestionItemData & {
    category: string;
    topic: string;
    children: QuestionItemData[];
};

function toItem(content: QuestionContent): QuestionItemData {
    return {
        uid: newUid(),
        type: content.type,
        difficulty: content.difficulty,
        body_html: content.body_html,
        explanation_html: content.explanation_html ?? '',
        rubric: content.rubric ?? '',
        default_points: content.default_points,
        options: content.options,
        accepted_answers:
            content.accepted_answers.length > 0
                ? content.accepted_answers
                : [''],
    };
}

/** Membuang field lokal (uid) dan data yang tidak relevan dengan jenis soal. */
function payload(item: QuestionItemData) {
    return {
        type: item.type,
        difficulty: item.difficulty,
        body_html: item.body_html,
        explanation_html: item.explanation_html || null,
        rubric: item.type === 'essay' ? item.rubric || null : null,
        default_points: item.type === 'case_study' ? null : item.default_points,
        options: ['multiple_choice', 'multiple_select', 'true_false'].includes(
            item.type,
        )
            ? item.options
            : [],
        accepted_answers:
            item.type === 'short_answer' ? item.accepted_answers : [],
    };
}

export default function QuestionForm({
    question,
    categories,
    usage,
    lockedUsage,
}: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const form = useForm<FormData>({
        ...(question ? toItem(question) : emptyItem()),
        category: question?.category?.id ?? '',
        topic: question?.topic ?? '',
        children: (question?.children ?? []).map(toItem),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const readOnly = question?.is_superseded ?? false;
    const title = question
        ? t('admin.questions.editTitle')
        : t('admin.questions.createTitle');

    const changeType = (type: QuestionType) =>
        form.setData((data) => ({
            ...data,
            type,
            options: defaultOptions(type),
            accepted_answers: type === 'short_answer' ? [''] : [],
            children:
                type === 'case_study' && data.children.length === 0
                    ? [emptyItem()]
                    : data.children,
        }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            category: data.category,
            topic: data.topic || null,
            ...payload(data),
            children:
                data.type === 'case_study' ? data.children.map(payload) : [],
        }));
        form.submit(
            question ? questions.update(question.id) : questions.store(),
            { preserveScroll: true },
        );
    };

    const setChild = (index: number, child: QuestionItemData) =>
        form.setData(
            'children',
            form.data.children.map((value, i) => (i === index ? child : value)),
        );

    return (
        <>
            <Head title={title} />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description={
                        question && (
                            <span className="flex flex-wrap items-center gap-2">
                                <QuestionStatusBadge status={question.status} />
                                {t('admin.questions.version', {
                                    version: question.version,
                                })}
                            </span>
                        )
                    }
                    actions={
                        question &&
                        !readOnly && (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.visit(
                                            questions.status.update(
                                                question.id,
                                            ),
                                            {
                                                data: {
                                                    status:
                                                        question.status ===
                                                        'active'
                                                            ? 'archived'
                                                            : 'active',
                                                },
                                                preserveScroll: true,
                                            },
                                        )
                                    }
                                >
                                    {question.status === 'active' ? (
                                        <Archive aria-hidden="true" />
                                    ) : (
                                        <RotateCcw aria-hidden="true" />
                                    )}
                                    {question.status === 'active'
                                        ? t('admin.questions.archive')
                                        : t('admin.questions.restore')}
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setConfirmDelete(true)}
                                >
                                    <Trash2 aria-hidden="true" />
                                    {t('admin.questions.delete')}
                                </Button>
                            </>
                        )
                    }
                />

                {readOnly && (
                    <Notice>{t('admin.questions.supersededNotice')}</Notice>
                )}
                {!readOnly && lockedUsage && (
                    <Notice>{t('admin.questions.lockedNotice')}</Notice>
                )}
                <InputError message={errors.question} />

                <div className="grid gap-6 lg:grid-cols-3">
                    <form
                        onSubmit={submit}
                        className="grid gap-6 lg:col-span-2"
                    >
                        <fieldset disabled={readOnly} className="grid gap-6">
                            <Card>
                                <CardContent className="grid gap-6">
                                    <div className="grid gap-6 sm:grid-cols-2">
                                        <FormField
                                            id="type"
                                            label={t(
                                                'admin.questions.fields.type',
                                            )}
                                            error={errors.type}
                                            description={
                                                question
                                                    ? t(
                                                          'admin.questions.fields.typeLocked',
                                                      )
                                                    : undefined
                                            }
                                        >
                                            {question ? (
                                                <Input
                                                    id="type"
                                                    value={t(
                                                        `status.questionType.${question.type}`,
                                                    )}
                                                    readOnly
                                                />
                                            ) : (
                                                <OptionSelect
                                                    id="type"
                                                    value={form.data.type}
                                                    onChange={(value) =>
                                                        changeType(
                                                            (value ??
                                                                'multiple_choice') as QuestionType,
                                                        )
                                                    }
                                                    options={questionTypes.map(
                                                        (type) => ({
                                                            value: type,
                                                            label: t(
                                                                `status.questionType.${type}`,
                                                            ),
                                                        }),
                                                    )}
                                                />
                                            )}
                                        </FormField>
                                        <FormField
                                            id="category"
                                            label={t(
                                                'admin.questions.fields.category',
                                            )}
                                            error={errors.category}
                                        >
                                            <OptionSelect
                                                id="category"
                                                value={
                                                    form.data.category || null
                                                }
                                                onChange={(value) =>
                                                    form.setData(
                                                        'category',
                                                        value ?? '',
                                                    )
                                                }
                                                options={categories.map(
                                                    (category) => ({
                                                        value: category.id,
                                                        label: category.name,
                                                    }),
                                                )}
                                                placeholder={t(
                                                    'admin.questions.fields.categoryPlaceholder',
                                                )}
                                                invalid={
                                                    errors.category !==
                                                    undefined
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            id="topic"
                                            label={t(
                                                'admin.questions.fields.topic',
                                            )}
                                            error={errors.topic}
                                            optional
                                        >
                                            <Input
                                                id="topic"
                                                value={form.data.topic}
                                                maxLength={150}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'topic',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            id="difficulty"
                                            label={t(
                                                'admin.questions.fields.difficulty',
                                            )}
                                            error={errors.difficulty}
                                        >
                                            <OptionSelect
                                                id="difficulty"
                                                value={form.data.difficulty}
                                                onChange={(value) =>
                                                    form.setData(
                                                        'difficulty',
                                                        (value ??
                                                            'medium') as Difficulty,
                                                    )
                                                }
                                                options={difficulties.map(
                                                    (difficulty) => ({
                                                        value: difficulty,
                                                        label: t(
                                                            `status.difficulty.${difficulty}`,
                                                        ),
                                                    }),
                                                )}
                                            />
                                        </FormField>
                                    </div>

                                    <QuestionItemEditor
                                        item={form.data}
                                        onChange={(item) =>
                                            form.setData((data) => ({
                                                ...data,
                                                ...item,
                                            }))
                                        }
                                        errors={errors}
                                        idPrefix="question"
                                        showPoints={
                                            form.data.type !== 'case_study'
                                        }
                                        bodyLabel={
                                            form.data.type === 'case_study'
                                                ? t(
                                                      'admin.questions.fields.caseBody',
                                                  )
                                                : t(
                                                      'admin.questions.fields.body',
                                                  )
                                        }
                                    />
                                </CardContent>
                            </Card>

                            {form.data.type === 'case_study' && (
                                <section
                                    className="grid gap-4"
                                    aria-labelledby="children-title"
                                >
                                    <div className="space-y-1">
                                        <h2
                                            id="children-title"
                                            className="text-lg font-semibold"
                                        >
                                            {t(
                                                'admin.questions.fields.children',
                                            )}
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            {t(
                                                'admin.questions.fields.childrenHelp',
                                            )}
                                        </p>
                                        <InputError message={errors.children} />
                                    </div>
                                    {form.data.children.map((child, index) => (
                                        <Card key={child.uid}>
                                            <CardHeader className="flex flex-row items-center justify-between gap-2">
                                                <CardTitle>
                                                    {t(
                                                        'admin.questions.fields.childTitle',
                                                        { number: index + 1 },
                                                    )}
                                                </CardTitle>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={t(
                                                        'admin.questions.fields.removeChild',
                                                        { number: index + 1 },
                                                    )}
                                                    title={t(
                                                        'admin.questions.fields.removeChild',
                                                        { number: index + 1 },
                                                    )}
                                                    disabled={
                                                        form.data.children
                                                            .length <= 1
                                                    }
                                                    onClick={() =>
                                                        form.setData(
                                                            'children',
                                                            form.data.children.filter(
                                                                (_, i) =>
                                                                    i !== index,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <X aria-hidden="true" />
                                                </Button>
                                            </CardHeader>
                                            <CardContent className="grid gap-6">
                                                <FormField
                                                    id={`child-${index}-type`}
                                                    label={t(
                                                        'admin.questions.fields.type',
                                                    )}
                                                    error={
                                                        errors[
                                                            `children.${index}.type`
                                                        ]
                                                    }
                                                >
                                                    <OptionSelect
                                                        id={`child-${index}-type`}
                                                        value={child.type}
                                                        onChange={(value) => {
                                                            const type =
                                                                (value ??
                                                                    'multiple_choice') as QuestionType;
                                                            setChild(index, {
                                                                ...child,
                                                                type,
                                                                options:
                                                                    defaultOptions(
                                                                        type,
                                                                    ),
                                                                accepted_answers:
                                                                    type ===
                                                                    'short_answer'
                                                                        ? ['']
                                                                        : [],
                                                            });
                                                        }}
                                                        options={questionTypes
                                                            .filter(
                                                                (type) =>
                                                                    type !==
                                                                    'case_study',
                                                            )
                                                            .map((type) => ({
                                                                value: type,
                                                                label: t(
                                                                    `status.questionType.${type}`,
                                                                ),
                                                            }))}
                                                    />
                                                </FormField>
                                                <QuestionItemEditor
                                                    item={child}
                                                    onChange={(item) =>
                                                        setChild(index, item)
                                                    }
                                                    errors={errors}
                                                    errorPrefix={`children.${index}.`}
                                                    idPrefix={`child-${child.uid}`}
                                                    showDifficulty
                                                />
                                            </CardContent>
                                        </Card>
                                    ))}
                                    <div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={
                                                form.data.children.length >= 20
                                            }
                                            onClick={() =>
                                                form.setData('children', [
                                                    ...form.data.children,
                                                    emptyItem(),
                                                ])
                                            }
                                        >
                                            <Plus aria-hidden="true" />
                                            {t(
                                                'admin.questions.fields.addChild',
                                            )}
                                        </Button>
                                    </div>
                                </section>
                            )}

                            {!readOnly && (
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        asChild
                                    >
                                        <Link href={questions.index()}>
                                            {t('common.cancel')}
                                        </Link>
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing && <Spinner />}
                                        {t('common.save')}
                                    </Button>
                                </div>
                            )}
                        </fieldset>
                    </form>

                    {question && (
                        <Card className="h-fit">
                            <CardHeader>
                                <CardTitle>
                                    {t('admin.questions.usage.title')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {usage.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('admin.questions.usage.empty')}
                                    </p>
                                ) : (
                                    <ul className="grid gap-3 text-sm">
                                        {usage.map((entry) => (
                                            <li
                                                key={`${entry.exam.id}-${entry.version}`}
                                                className="grid gap-1"
                                            >
                                                <Link
                                                    href={exams.show(
                                                        entry.exam.id,
                                                    )}
                                                    className="font-medium text-primary hover:underline"
                                                >
                                                    {entry.exam.title}
                                                </Link>
                                                <span className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                                    {t(
                                                        'admin.questions.version',
                                                        {
                                                            version:
                                                                entry.version,
                                                        },
                                                    )}
                                                    <ExamStatusBadge
                                                        status={
                                                            entry.exam.status
                                                        }
                                                    />
                                                    {entry.exam.is_locked && (
                                                        <LockedBadge />
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>

            {question && (
                <ConfirmDialog
                    open={confirmDelete}
                    onOpenChange={setConfirmDelete}
                    title={t('admin.questions.deleteTitle')}
                    description={t('admin.questions.deleteDescription')}
                    confirmLabel={t('common.delete')}
                    destructive
                    onConfirm={() =>
                        router.visit(questions.destroy(question.id), {
                            onFinish: () => setConfirmDelete(false),
                        })
                    }
                />
            )}
        </>
    );
}

function Notice({ children }: { children: React.ReactNode }) {
    return (
        <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
            {children}
        </p>
    );
}

QuestionForm.layout = {
    breadcrumbs: [
        { title: t('admin.questions.title'), href: questions.index() },
    ],
};
