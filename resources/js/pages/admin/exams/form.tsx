import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { FormField } from '@/components/app/form-field';
import { MultiCheckbox } from '@/components/app/multi-checkbox';
import { OptionSelect } from '@/components/app/option-select';
import { PageHeader } from '@/components/app/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import exams from '@/routes/admin/exams';
import type {
    ExamDetail,
    ExamStatus,
    ExamType,
    MultiSelectScoring,
    NavigationMode,
    ResultRelease,
    ScorePolicy,
} from '@/types';

type Props = {
    exam: ExamDetail | null;
    programs: { id: string; name: string }[];
};

const examTypes: ExamType[] = [
    'pre_test',
    'post_test',
    'general_knowledge',
    'technical',
    'product_knowledge',
    'sop',
    'case_study',
];
const statuses: ExamStatus[] = ['draft', 'published', 'archived'];

function options<T extends string>(
    values: T[],
    group:
        | 'navigation'
        | 'scorePolicy'
        | 'multiSelectScoring'
        | 'resultRelease'
        | 'examType'
        | 'exam',
) {
    return values.map((value) => ({
        value,
        label: t(`status.${group}.${value}` as Parameters<typeof t>[0]),
    }));
}

export default function ExamForm({ exam, programs }: Props) {
    const form = useForm({
        title: exam?.title ?? '',
        description: exam?.description ?? '',
        type: exam?.type ?? ('general_knowledge' as ExamType),
        duration_minutes: exam?.duration_minutes.toString() ?? '60',
        passing_grade: exam?.passing_grade ?? '70',
        max_attempts: exam?.max_attempts.toString() ?? '1',
        opens_at: exam?.opens_at_input ?? '',
        closes_at: exam?.closes_at_input ?? '',
        shuffle_questions: exam?.shuffle_questions ?? false,
        shuffle_options: exam?.shuffle_options ?? false,
        navigation: exam?.navigation ?? ('free' as NavigationMode),
        score_policy: exam?.score_policy ?? ('highest' as ScorePolicy),
        multi_select_scoring:
            exam?.multi_select_scoring ??
            ('all_or_nothing' as MultiSelectScoring),
        result_release: exam?.result_release ?? ('immediate' as ResultRelease),
        show_review: exam?.show_review ?? false,
        status: exam?.status ?? ('draft' as ExamStatus),
        prerequisite_programs:
            exam?.prerequisite_programs?.map((program) => program.id) ?? [],
        related_programs:
            exam?.related_programs?.map((program) => program.id) ?? [],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const title = exam
        ? t('admin.exams.editTitle')
        : t('admin.exams.createTitle');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            opens_at: data.opens_at || null,
            closes_at: data.closes_at || null,
        }));
        form.submit(exam ? exams.update(exam.id) : exams.store());
    };

    const checkbox = (
        key: 'shuffle_questions' | 'shuffle_options' | 'show_review',
        label: string,
    ) => (
        <div className="flex items-start gap-3">
            <Checkbox
                id={key}
                checked={form.data[key]}
                onCheckedChange={(checked) =>
                    form.setData(key, checked === true)
                }
            />
            <Label htmlFor={key} className="leading-snug font-normal">
                {label}
            </Label>
        </div>
    );

    return (
        <>
            <Head title={title} />

            <form
                onSubmit={submit}
                className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader title={title} />

                <Section title={t('admin.exams.fields.sections.general')}>
                    <FormField
                        id="title"
                        label={t('admin.exams.fields.title')}
                        error={errors.title}
                    >
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="description"
                        label={t('admin.exams.fields.description')}
                        error={errors.description}
                        optional
                    >
                        <Textarea
                            id="description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            maxLength={5000}
                        />
                    </FormField>
                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            id="type"
                            label={t('admin.exams.fields.type')}
                            error={errors.type}
                        >
                            <OptionSelect
                                id="type"
                                value={form.data.type}
                                onChange={(value) =>
                                    form.setData(
                                        'type',
                                        (value ??
                                            'general_knowledge') as ExamType,
                                    )
                                }
                                options={options(examTypes, 'examType')}
                            />
                        </FormField>
                        <FormField
                            id="status"
                            label={t('admin.exams.fields.status')}
                            error={errors.status}
                            description={t('admin.exams.fields.statusHelp')}
                        >
                            <OptionSelect
                                id="status"
                                value={form.data.status}
                                onChange={(value) =>
                                    form.setData(
                                        'status',
                                        (value ?? 'draft') as ExamStatus,
                                    )
                                }
                                options={options(statuses, 'exam')}
                            />
                        </FormField>
                    </div>
                </Section>

                <Section title={t('admin.exams.fields.sections.schedule')}>
                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            id="opens_at"
                            label={t('admin.exams.fields.opensAt')}
                            error={errors.opens_at}
                            optional
                        >
                            <Input
                                id="opens_at"
                                type="datetime-local"
                                value={form.data.opens_at}
                                onChange={(event) =>
                                    form.setData('opens_at', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="closes_at"
                            label={t('admin.exams.fields.closesAt')}
                            error={errors.closes_at}
                            optional
                        >
                            <Input
                                id="closes_at"
                                type="datetime-local"
                                value={form.data.closes_at}
                                min={form.data.opens_at || undefined}
                                onChange={(event) =>
                                    form.setData(
                                        'closes_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </div>
                    <p className="-mt-3 text-sm text-muted-foreground">
                        {t('admin.exams.fields.scheduleHelp')}
                    </p>
                    <div className="grid gap-6 sm:grid-cols-3">
                        <FormField
                            id="duration_minutes"
                            label={t('admin.exams.fields.duration')}
                            error={errors.duration_minutes}
                        >
                            <Input
                                id="duration_minutes"
                                type="number"
                                min={1}
                                max={600}
                                inputMode="numeric"
                                value={form.data.duration_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'duration_minutes',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            id="max_attempts"
                            label={t('admin.exams.fields.maxAttempts')}
                            error={errors.max_attempts}
                        >
                            <Input
                                id="max_attempts"
                                type="number"
                                min={1}
                                max={10}
                                inputMode="numeric"
                                value={form.data.max_attempts}
                                onChange={(event) =>
                                    form.setData(
                                        'max_attempts',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            id="passing_grade"
                            label={t('admin.exams.fields.passingGrade')}
                            error={errors.passing_grade}
                        >
                            <Input
                                id="passing_grade"
                                type="number"
                                min={0}
                                max={100}
                                step="0.01"
                                inputMode="decimal"
                                value={form.data.passing_grade}
                                onChange={(event) =>
                                    form.setData(
                                        'passing_grade',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </FormField>
                    </div>
                </Section>

                <Section title={t('admin.exams.fields.sections.behaviour')}>
                    {checkbox(
                        'shuffle_questions',
                        t('admin.exams.fields.shuffleQuestions'),
                    )}
                    {checkbox(
                        'shuffle_options',
                        t('admin.exams.fields.shuffleOptions'),
                    )}
                    <FormField
                        id="navigation"
                        label={t('admin.exams.fields.navigation')}
                        error={errors.navigation}
                    >
                        <OptionSelect
                            id="navigation"
                            value={form.data.navigation}
                            onChange={(value) =>
                                form.setData(
                                    'navigation',
                                    (value ?? 'free') as NavigationMode,
                                )
                            }
                            options={options<NavigationMode>(
                                ['free', 'forward_only'],
                                'navigation',
                            )}
                        />
                    </FormField>
                </Section>

                <Section title={t('admin.exams.fields.sections.scoring')}>
                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            id="score_policy"
                            label={t('admin.exams.fields.scorePolicy')}
                            error={errors.score_policy}
                        >
                            <OptionSelect
                                id="score_policy"
                                value={form.data.score_policy}
                                onChange={(value) =>
                                    form.setData(
                                        'score_policy',
                                        (value ?? 'highest') as ScorePolicy,
                                    )
                                }
                                options={options<ScorePolicy>(
                                    ['highest', 'latest'],
                                    'scorePolicy',
                                )}
                            />
                        </FormField>
                        <FormField
                            id="multi_select_scoring"
                            label={t('admin.exams.fields.multiSelectScoring')}
                            error={errors.multi_select_scoring}
                        >
                            <OptionSelect
                                id="multi_select_scoring"
                                value={form.data.multi_select_scoring}
                                onChange={(value) =>
                                    form.setData(
                                        'multi_select_scoring',
                                        (value ??
                                            'all_or_nothing') as MultiSelectScoring,
                                    )
                                }
                                options={options<MultiSelectScoring>(
                                    ['all_or_nothing', 'partial'],
                                    'multiSelectScoring',
                                )}
                            />
                        </FormField>
                        <FormField
                            id="result_release"
                            label={t('admin.exams.fields.resultRelease')}
                            error={errors.result_release}
                        >
                            <OptionSelect
                                id="result_release"
                                value={form.data.result_release}
                                onChange={(value) =>
                                    form.setData(
                                        'result_release',
                                        (value ?? 'immediate') as ResultRelease,
                                    )
                                }
                                options={options<ResultRelease>(
                                    ['immediate', 'after_close', 'manual'],
                                    'resultRelease',
                                )}
                            />
                        </FormField>
                    </div>
                    {checkbox(
                        'show_review',
                        t('admin.exams.fields.showReview'),
                    )}
                </Section>

                <Section title={t('admin.exams.fields.sections.programs')}>
                    <FormField
                        id="prerequisite_programs"
                        label={t('admin.exams.fields.prerequisites')}
                        error={errors.prerequisite_programs}
                        description={t('admin.exams.fields.prerequisitesHelp')}
                    >
                        <MultiCheckbox
                            idPrefix="prerequisite"
                            legend={t('admin.exams.fields.prerequisites')}
                            options={programs}
                            value={form.data.prerequisite_programs}
                            onChange={(value) =>
                                form.setData('prerequisite_programs', value)
                            }
                            emptyText={t('admin.programs.empty')}
                        />
                    </FormField>
                    <FormField
                        id="related_programs"
                        label={t('admin.exams.fields.relatedPrograms')}
                        error={errors.related_programs}
                    >
                        <MultiCheckbox
                            idPrefix="related"
                            legend={t('admin.exams.fields.relatedPrograms')}
                            options={programs}
                            value={form.data.related_programs}
                            onChange={(value) =>
                                form.setData('related_programs', value)
                            }
                            emptyText={t('admin.programs.empty')}
                        />
                    </FormField>
                </Section>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" asChild>
                        <Link href={exam ? exams.show(exam.id) : exams.index()}>
                            {t('common.cancel')}
                        </Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {t('common.save')}
                    </Button>
                </div>
            </form>
        </>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-6">{children}</CardContent>
        </Card>
    );
}

ExamForm.layout = {
    breadcrumbs: [{ title: t('admin.exams.title'), href: exams.index() }],
};
