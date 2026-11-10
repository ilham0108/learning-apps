import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { FormField } from '@/components/app/form-field';
import { OptionSelect } from '@/components/app/option-select';
import {
    EvaluationStatusBadge,
    StatusBadge,
} from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import applications from '@/routes/admin/applications';
import type {
    ApplicationDetail,
    CandidateEvaluation,
    EvaluationCriterion,
    Recommendation,
} from '@/types';

const criteria: EvaluationCriterion[] = [
    'exam',
    'learning',
    'interview',
    'attitude',
    'other',
];
const recommendations: Recommendation[] = [
    'strong_yes',
    'yes',
    'consider',
    'no',
];
const scoreOptions = ['1', '2', '3', '4', '5'].map((value) => ({
    value,
    label: value,
}));

export function EvaluationSection({
    application,
}: {
    application: ApplicationDetail;
}) {
    const mine = application.evaluations.find(
        (evaluation) => evaluation.is_mine,
    );
    const others = application.evaluations.filter(
        (evaluation) => !evaluation.is_mine,
    );

    return (
        <section
            className="space-y-4"
            aria-labelledby={`evaluation-${application.id}`}
        >
            <div className="space-y-1">
                <h3
                    id={`evaluation-${application.id}`}
                    className="text-sm font-medium"
                >
                    {t('admin.applications.evaluation.title')}
                </h3>
                <p className="text-xs text-muted-foreground">
                    {t('admin.applications.evaluation.description')}
                </p>
            </div>

            <div className="space-y-2">
                <div className="flex items-center gap-2">
                    <h4 className="text-sm font-medium">
                        {t('admin.applications.evaluation.mine')}
                    </h4>
                    {mine && <EvaluationStatusBadge status={mine.status} />}
                </div>
                {mine?.status === 'final' ? (
                    <EvaluationSummary evaluation={mine} />
                ) : (
                    <EvaluationForm
                        application={application}
                        evaluation={mine}
                    />
                )}
            </div>

            <div className="space-y-2">
                <h4 className="text-sm font-medium">
                    {t('admin.applications.evaluation.others')}
                </h4>
                {others.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('admin.applications.evaluation.empty')}
                    </p>
                ) : (
                    others.map((evaluation) => (
                        <div
                            key={evaluation.id}
                            className="space-y-2 rounded-lg border p-3"
                        >
                            <div className="flex items-center gap-2 text-sm">
                                <span className="font-medium">
                                    {evaluation.evaluator ??
                                        t('common.notAvailable')}
                                </span>
                                <EvaluationStatusBadge
                                    status={evaluation.status}
                                />
                            </div>
                            <EvaluationSummary evaluation={evaluation} />
                        </div>
                    ))
                )}
            </div>
        </section>
    );
}

function EvaluationSummary({
    evaluation,
}: {
    evaluation: CandidateEvaluation;
}) {
    const { displayTimezone } = usePage().props;

    return (
        <div className="space-y-3 text-sm">
            <dl className="grid gap-2 sm:grid-cols-2">
                {evaluation.scores.map((score) => (
                    <div key={score.criterion}>
                        <dt className="text-muted-foreground">
                            {t(`status.criterion.${score.criterion}`)}
                        </dt>
                        <dd className="font-medium">
                            {score.score !== null
                                ? `${score.score}/5`
                                : t('admin.applications.evaluation.scoreNone')}
                        </dd>
                        {score.note && (
                            <dd className="text-xs text-muted-foreground">
                                {score.note}
                            </dd>
                        )}
                    </div>
                ))}
            </dl>
            {evaluation.recommendation && (
                <StatusBadge
                    label={t(
                        `status.recommendation.${evaluation.recommendation}`,
                    )}
                    tone={
                        evaluation.recommendation === 'no'
                            ? 'danger'
                            : evaluation.recommendation === 'consider'
                              ? 'warning'
                              : 'success'
                    }
                />
            )}
            {evaluation.summary && (
                <p className="whitespace-pre-line">{evaluation.summary}</p>
            )}
            {evaluation.finalized_at && (
                <p className="text-xs text-muted-foreground">
                    {t('admin.applications.evaluation.finalizedAt', {
                        date: formatDateTime(
                            evaluation.finalized_at,
                            displayTimezone,
                        ),
                    })}
                </p>
            )}
        </div>
    );
}

type EvaluationFormData = {
    scores: Record<EvaluationCriterion, { score: number | null; note: string }>;
    recommendation: Recommendation | null;
    summary: string;
    finalize: boolean;
};

function EvaluationForm({
    application,
    evaluation,
}: {
    application: ApplicationDetail;
    evaluation: CandidateEvaluation | undefined;
}) {
    const [confirmFinalize, setConfirmFinalize] = useState(false);
    const form = useForm<EvaluationFormData>({
        scores: Object.fromEntries(
            criteria.map((criterion) => {
                const existing = evaluation?.scores.find(
                    (score) => score.criterion === criterion,
                );

                return [
                    criterion,
                    {
                        score: existing?.score ?? null,
                        note: existing?.note ?? '',
                    },
                ];
            }),
        ) as EvaluationFormData['scores'],
        recommendation: evaluation?.recommendation ?? null,
        summary: evaluation?.summary ?? '',
        finalize: false,
    });
    const errors = form.errors as Record<string, string | undefined>;

    const save = (finalize: boolean) => {
        form.transform((data) => ({ ...data, finalize }));
        form.submit(applications.evaluation.update(application.id), {
            preserveScroll: true,
            onSuccess: () => setConfirmFinalize(false),
        });
    };

    const submitDraft = (event: FormEvent) => {
        event.preventDefault();
        save(false);
    };

    const setScore = (
        criterion: EvaluationCriterion,
        changes: Partial<EvaluationFormData['scores'][EvaluationCriterion]>,
    ) =>
        form.setData('scores', {
            ...form.data.scores,
            [criterion]: { ...form.data.scores[criterion], ...changes },
        });

    const prefix = `eval-${application.id}`;

    return (
        <form onSubmit={submitDraft} className="grid gap-4">
            <div className="grid gap-4 md:grid-cols-2">
                {criteria.map((criterion) => (
                    <fieldset
                        key={criterion}
                        className="grid gap-2 rounded-lg border p-3"
                    >
                        <legend className="px-1 text-sm font-medium">
                            {t(`status.criterion.${criterion}`)}
                        </legend>
                        <FormField
                            id={`${prefix}-${criterion}-score`}
                            label={t('admin.applications.evaluation.score')}
                            error={errors[`scores.${criterion}.score`]}
                        >
                            <OptionSelect
                                id={`${prefix}-${criterion}-score`}
                                value={
                                    form.data.scores[
                                        criterion
                                    ].score?.toString() ?? null
                                }
                                onChange={(value) =>
                                    setScore(criterion, {
                                        score:
                                            value === null
                                                ? null
                                                : Number(value),
                                    })
                                }
                                options={scoreOptions}
                                allowAll={false}
                                placeholder={t(
                                    'admin.applications.evaluation.scoreNone',
                                )}
                            />
                        </FormField>
                        <FormField
                            id={`${prefix}-${criterion}-note`}
                            label={t('admin.applications.evaluation.note')}
                            error={errors[`scores.${criterion}.note`]}
                            optional
                        >
                            <Textarea
                                id={`${prefix}-${criterion}-note`}
                                value={form.data.scores[criterion].note}
                                onChange={(event) =>
                                    setScore(criterion, {
                                        note: event.target.value,
                                    })
                                }
                                maxLength={2000}
                                className="min-h-12"
                            />
                        </FormField>
                    </fieldset>
                ))}
            </div>

            <FormField
                id={`${prefix}-recommendation`}
                label={t('admin.applications.evaluation.recommendation')}
                error={form.errors.recommendation}
            >
                <OptionSelect
                    id={`${prefix}-recommendation`}
                    value={form.data.recommendation}
                    onChange={(value) =>
                        form.setData(
                            'recommendation',
                            value as Recommendation | null,
                        )
                    }
                    options={recommendations.map((value) => ({
                        value,
                        label: t(`status.recommendation.${value}`),
                    }))}
                    placeholder={t(
                        'admin.applications.evaluation.recommendationPlaceholder',
                    )}
                />
            </FormField>

            <FormField
                id={`${prefix}-summary`}
                label={t('admin.applications.evaluation.summary')}
                error={form.errors.summary}
                optional
            >
                <Textarea
                    id={`${prefix}-summary`}
                    value={form.data.summary}
                    onChange={(event) =>
                        form.setData('summary', event.target.value)
                    }
                    maxLength={5000}
                />
            </FormField>

            <InputError message={errors.evaluation} />

            <div className="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                >
                    {form.processing && <Spinner />}
                    {t('admin.applications.evaluation.saveDraft')}
                </Button>
                <Button
                    type="button"
                    disabled={
                        form.processing || form.data.recommendation === null
                    }
                    onClick={() => setConfirmFinalize(true)}
                >
                    {t('admin.applications.evaluation.finalize')}
                </Button>
            </div>

            <ConfirmDialog
                open={confirmFinalize}
                onOpenChange={setConfirmFinalize}
                title={t('admin.applications.evaluation.finalizeTitle')}
                description={t(
                    'admin.applications.evaluation.finalizeDescription',
                )}
                confirmLabel={t('admin.applications.evaluation.finalize')}
                processing={form.processing}
                onConfirm={() => save(true)}
            />
        </form>
    );
}
