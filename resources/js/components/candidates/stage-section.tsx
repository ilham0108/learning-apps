import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { FormField } from '@/components/app/form-field';
import { OptionSelect } from '@/components/app/option-select';
import { RecruitmentStageBadge } from '@/components/app/status-badge';
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
import applications from '@/routes/admin/applications';
import type { ApplicationDetail, RecruitmentStage } from '@/types';

/** Sama dengan RecruitmentStage::requiresReason() di server; server tetap memvalidasi ulang. */
function requiresReason(from: RecruitmentStage, to: RecruitmentStage): boolean {
    return (
        to === 'rejected' ||
        to === 'withdrawn' ||
        (from === 'rejected' && to === 'under_review')
    );
}

export function StageSection({
    application,
}: {
    application: ApplicationDetail;
}) {
    const { displayTimezone } = usePage().props;
    const [open, setOpen] = useState(false);

    return (
        <section
            className="space-y-4"
            aria-labelledby={`stage-${application.id}`}
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="space-y-1">
                    <h3
                        id={`stage-${application.id}`}
                        className="text-sm font-medium text-muted-foreground"
                    >
                        {t('admin.applications.stage.title')}
                    </h3>
                    <div className="flex flex-wrap items-center gap-2">
                        <RecruitmentStageBadge stage={application.stage} />
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
                {application.allowed_stages.length > 0 ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setOpen(true)}
                    >
                        {t('admin.applications.stage.change')}
                    </Button>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        {t('admin.applications.stage.noTransitions')}
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
                                        <RecruitmentStageBadge
                                            stage={entry.from}
                                        />
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
                                <RecruitmentStageBadge stage={entry.to} />
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
                                <p className="mt-1 rounded-md bg-muted px-2 py-1 text-xs">
                                    {entry.reason}
                                </p>
                            )}
                        </li>
                    ))}
                </ol>
            </div>

            {open && (
                <ChangeStageDialog
                    application={application}
                    onClose={() => setOpen(false)}
                />
            )}
        </section>
    );
}

function ChangeStageDialog({
    application,
    onClose,
}: {
    application: ApplicationDetail;
    onClose: () => void;
}) {
    const form = useForm<{ stage: RecruitmentStage | ''; reason: string }>({
        stage: '',
        reason: '',
    });
    const needsReason =
        form.data.stage !== '' &&
        requiresReason(application.stage, form.data.stage);

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
                <form onSubmit={submit} className="grid gap-6">
                    <DialogHeader>
                        <DialogTitle>
                            {t('admin.applications.stage.changeTitle')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('admin.applications.stage.changeDescription')}
                        </DialogDescription>
                    </DialogHeader>

                    {application.campaign.status === 'closed' && (
                        <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
                            {t('admin.applications.stage.closedCampaign')}
                        </p>
                    )}

                    <FormField
                        id="stage"
                        label={t('admin.applications.stage.target')}
                        error={form.errors.stage}
                    >
                        <OptionSelect
                            id="stage"
                            value={form.data.stage || null}
                            onChange={(value) =>
                                form.setData(
                                    'stage',
                                    (value ?? '') as RecruitmentStage | '',
                                )
                            }
                            options={application.allowed_stages.map(
                                (stage) => ({
                                    value: stage,
                                    label: t(
                                        `status.recruitmentStage.${stage}`,
                                    ),
                                }),
                            )}
                            placeholder={t(
                                'admin.applications.stage.targetPlaceholder',
                            )}
                        />
                    </FormField>

                    <FormField
                        id="reason"
                        label={t('common.reason')}
                        error={form.errors.reason}
                        optional={!needsReason}
                        description={
                            needsReason
                                ? t('admin.applications.stage.reasonRequired')
                                : undefined
                        }
                    >
                        <Textarea
                            id="reason"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            placeholder={t(
                                'admin.applications.stage.reasonPlaceholder',
                            )}
                            required={needsReason}
                            maxLength={1000}
                        />
                    </FormField>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            variant={
                                form.data.stage === 'rejected'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={form.processing || form.data.stage === ''}
                        >
                            {form.processing && <Spinner />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
