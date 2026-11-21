import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { FormField } from '@/components/app/form-field';
import {
    RecruitmentStageBadge,
    StatusBadge,
} from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { CompletedIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';
import applications from '@/routes/admin/applications';
import type { ApplicationDetail, StageOption } from '@/types';

function actionLabel(option: StageOption): string {
    switch (option.kind) {
        case 'advance':
            return t('admin.applications.stage.actions.advance', {
                stage: option.label,
            });
        case 'correction':
            return t('admin.applications.stage.actions.correction', {
                stage: option.label,
            });
        default:
            return option.target === 'hired'
                ? t('admin.applications.stage.actions.hired')
                : option.target === 'rejected'
                  ? t('admin.applications.stage.actions.rejected')
                  : t('admin.applications.stage.actions.withdrawn');
    }
}

/** Alur tahap proses sebagai urutan langkah; status tiap langkah juga tertulis untuk pembaca layar. */
function PipelineSteps({ application }: { application: ApplicationDetail }) {
    const currentIndex = application.pipeline.findIndex(
        (stage) => stage.is_current,
    );
    const afterProcess = ['hired'].includes(application.stage);

    if (application.pipeline.length === 0) {
        return null;
    }

    return (
        <ol
            aria-label={t('admin.applications.stage.pipelineLabel')}
            className="flex flex-wrap items-center gap-1.5 text-xs"
        >
            {application.pipeline.map((stage, index) => {
                const done =
                    afterProcess || (currentIndex >= 0 && index < currentIndex);
                const current = stage.is_current;

                return (
                    <li key={stage.id} className="flex items-center gap-1.5">
                        <span
                            aria-current={current ? 'step' : undefined}
                            className={cn(
                                'inline-flex items-center gap-1 rounded-full border px-2.5 py-1',
                                current &&
                                    'border-primary bg-primary text-primary-foreground',
                                done &&
                                    'border-primary/40 bg-primary/10 text-primary',
                                !current && !done && 'text-muted-foreground',
                            )}
                        >
                            {done && (
                                <CompletedIcon
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            )}
                            {stage.name}
                            <span className="sr-only">
                                ,{' '}
                                {done
                                    ? t('admin.applications.stage.stepDone')
                                    : current
                                      ? t(
                                            'admin.applications.stage.stepCurrent',
                                        )
                                      : t(
                                            'admin.applications.stage.stepUpcoming',
                                        )}
                            </span>
                        </span>
                        {index < application.pipeline.length - 1 && (
                            <span
                                className="text-muted-foreground"
                                aria-hidden="true"
                            >
                                →
                            </span>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

export function StageSection({
    application,
}: {
    application: ApplicationDetail;
}) {
    const { displayTimezone } = usePage().props;
    const [selected, setSelected] = useState<StageOption | null>(null);

    return (
        <section
            className="space-y-4"
            aria-labelledby={`stage-${application.id}`}
        >
            <div className="space-y-3">
                <div className="space-y-1">
                    <h3
                        id={`stage-${application.id}`}
                        className="text-sm font-medium text-muted-foreground"
                    >
                        {t('admin.applications.stage.title')}
                    </h3>
                    <div className="flex flex-wrap items-center gap-2">
                        <RecruitmentStageBadge
                            stage={application.stage}
                            label={application.stage_label}
                        />
                        <span className="text-xs text-muted-foreground">
                            {t('admin.applications.stage.since', {
                                date: formatDateTime(
                                    application.stage_changed_at,
                                    displayTimezone,
                                ),
                            })}
                        </span>
                    </div>
                </div>

                <PipelineSteps application={application} />

                {application.stage_options.length > 0 ? (
                    <div className="space-y-2">
                        <div className="flex flex-wrap gap-2">
                            {application.stage_options.map((option) => (
                                <Button
                                    key={option.target}
                                    size="sm"
                                    variant={
                                        option.target === 'rejected'
                                            ? 'destructive'
                                            : option.kind === 'advance' ||
                                                option.target === 'hired'
                                              ? 'default'
                                              : 'outline'
                                    }
                                    onClick={() => setSelected(option)}
                                >
                                    {actionLabel(option)}
                                </Button>
                            ))}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {t('admin.applications.stage.sequentialHint')}
                        </p>
                    </div>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        {application.campaign.status === 'closed'
                            ? t('admin.applications.stage.closedCampaign')
                            : t('admin.applications.stage.noTransitions')}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <h4 className="text-sm font-medium">
                    {t('admin.applications.history.title')}
                </h4>
                <ol className="space-y-3 border-l pl-4">
                    {application.history.map((entry, index) => (
                        <li key={index} className="relative text-sm">
                            <span
                                className="absolute top-1.5 -left-[1.3rem] size-2 rounded-full bg-primary"
                                aria-hidden="true"
                            />
                            <div className="flex flex-wrap items-center gap-1.5">
                                {entry.from ? (
                                    <>
                                        <StatusBadge label={entry.from} />
                                        <span aria-hidden="true">→</span>
                                    </>
                                ) : (
                                    <span className="text-xs text-muted-foreground">
                                        {t(
                                            'admin.applications.history.initial',
                                        )}
                                        :
                                    </span>
                                )}
                                <StatusBadge label={entry.to} tone="info" />
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {formatDateTime(
                                    entry.created_at,
                                    displayTimezone,
                                )}{' '}
                                ·{' '}
                                {entry.changed_by
                                    ? t('admin.applications.history.by', {
                                          name: entry.changed_by,
                                      })
                                    : t(
                                          'admin.applications.history.byCandidate',
                                      )}
                            </p>
                            {entry.reason && (
                                <p className="mt-1 text-sm">{entry.reason}</p>
                            )}
                        </li>
                    ))}
                </ol>
            </div>

            {selected && (
                <MoveDialog
                    key={selected.target}
                    application={application}
                    option={selected}
                    onClose={() => setSelected(null)}
                />
            )}
        </section>
    );
}

function MoveDialog({
    application,
    option,
    onClose,
}: {
    application: ApplicationDetail;
    option: StageOption;
    onClose: () => void;
}) {
    const form = useForm({ target: option.target, reason: '' });
    const reasonId = `reason-${application.id}`;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(applications.stage.update(application.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('admin.applications.stage.confirmTitle', {
                            stage: option.label,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('admin.applications.stage.changeDescription')}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    {form.errors.target && (
                        <p className="text-sm text-red-700 dark:text-red-400">
                            {form.errors.target}
                        </p>
                    )}
                    <FormField
                        id={reasonId}
                        label={t('common.reason')}
                        error={form.errors.reason}
                        optional={!option.requires_reason}
                        description={
                            option.requires_reason
                                ? t('admin.applications.stage.reasonRequired')
                                : undefined
                        }
                    >
                        <Textarea
                            id={reasonId}
                            rows={3}
                            maxLength={1000}
                            required={option.requires_reason}
                            placeholder={t(
                                'admin.applications.stage.reasonPlaceholder',
                            )}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </FormField>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={form.processing}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            variant={
                                option.target === 'rejected'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            {actionLabel(option)}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
