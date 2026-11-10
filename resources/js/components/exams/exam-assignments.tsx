import { Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, Trash2, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { Column } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { FormField } from '@/components/app/form-field';
import { OptionSelect } from '@/components/app/option-select';
import { StatusBadge } from '@/components/app/status-badge';
import type { AssignOptions } from '@/components/programs/assignment-panel';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import candidates from '@/routes/admin/candidates';
import examAssignments from '@/routes/admin/exam-assignments';
import exams from '@/routes/admin/exams';
import type { ExamAssignmentRow, ExamDetail } from '@/types';

export function ExamAssignments({
    exam,
    rows,
    options,
}: {
    exam: ExamDetail;
    rows: ExamAssignmentRow[];
    options: AssignOptions;
}) {
    const { displayTimezone } = usePage().props;
    const [assignOpen, setAssignOpen] = useState(false);
    const [scheduling, setScheduling] = useState<ExamAssignmentRow | null>(
        null,
    );
    const [revoking, setRevoking] = useState<ExamAssignmentRow | null>(null);
    const isPublished = exam.status === 'published';

    const columns: Column<ExamAssignmentRow>[] = [
        {
            key: 'candidate',
            header: t('admin.programs.assignments.columns.candidate'),
            cell: (row) => (
                <div className="min-w-40">
                    <Link
                        href={candidates.show(row.candidate.id)}
                        className="font-medium text-primary hover:underline"
                    >
                        {row.candidate.name}
                    </Link>
                    <div className="font-mono text-xs text-muted-foreground">
                        {row.candidate.candidate_number}
                    </div>
                </div>
            ),
        },
        {
            key: 'campaign',
            header: t('admin.programs.assignments.columns.campaign'),
            cell: (row) => row.campaign,
        },
        {
            key: 'schedule',
            header: t('admin.exams.assignments.schedule'),
            className: 'whitespace-nowrap text-xs',
            cell: (row) => (
                <div className="grid gap-1">
                    <span>
                        {row.opens_at || row.closes_at
                            ? formatDateTime(row.opens_at, displayTimezone)
                            : t('admin.exams.summary.noSchedule')}
                    </span>
                    {row.closes_at && (
                        <span>
                            {formatDateTime(row.closes_at, displayTimezone)}
                        </span>
                    )}
                    {row.has_override && (
                        <StatusBadge
                            label={t('admin.exams.assignments.custom')}
                            tone="info"
                        />
                    )}
                </div>
            ),
        },
        {
            key: 'attempts',
            header: t('admin.exams.assignments.attempts'),
            className: 'whitespace-nowrap',
            cell: (row) =>
                t('admin.exams.assignments.attemptsValue', {
                    used: row.attempts_count,
                    max: row.max_attempts,
                }),
        },
        {
            key: 'actions',
            header: t('common.actions'),
            cell: (row) => (
                <div className="flex gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        aria-label={t('admin.exams.assignments.editSchedule')}
                        title={t('admin.exams.assignments.editSchedule')}
                        onClick={() => setScheduling(row)}
                    >
                        <CalendarClock aria-hidden="true" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        aria-label={t('admin.exams.assignments.revoke')}
                        title={t('admin.exams.assignments.revoke')}
                        disabled={row.attempts_count > 0}
                        onClick={() => setRevoking(row)}
                    >
                        <Trash2 aria-hidden="true" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <section className="space-y-4" aria-labelledby="exam-assignments-title">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h2
                        id="exam-assignments-title"
                        className="text-lg font-semibold"
                    >
                        {t('admin.exams.assignments.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {isPublished
                            ? t('admin.exams.assignments.description')
                            : t('admin.exams.assignments.notPublished')}
                    </p>
                </div>
                <Button
                    onClick={() => setAssignOpen(true)}
                    disabled={!isPublished}
                >
                    <UserPlus aria-hidden="true" />
                    {t('admin.programs.assignments.assign')}
                </Button>
            </div>

            <DataTable
                columns={columns}
                rows={rows}
                caption={t('admin.exams.assignments.title')}
                empty={
                    <EmptyState title={t('admin.programs.assignments.empty')} />
                }
            />

            {assignOpen && (
                <AssignDialog
                    examId={exam.id}
                    options={options}
                    onClose={() => setAssignOpen(false)}
                />
            )}
            {scheduling && (
                <ScheduleDialog
                    row={scheduling}
                    onClose={() => setScheduling(null)}
                />
            )}

            <ConfirmDialog
                open={revoking !== null}
                onOpenChange={(open) => !open && setRevoking(null)}
                title={t('admin.exams.assignments.revokeTitle')}
                description={t('admin.exams.assignments.revokeDescription')}
                confirmLabel={t('admin.exams.assignments.revoke')}
                destructive
                onConfirm={() =>
                    revoking &&
                    router.visit(examAssignments.destroy(revoking.id), {
                        preserveScroll: true,
                        onSuccess: () => setRevoking(null),
                    })
                }
            />
        </section>
    );
}

function AssignDialog({
    examId,
    options,
    onClose,
}: {
    examId: string;
    options: AssignOptions;
    onClose: () => void;
}) {
    const form = useForm<{
        via: 'campaign' | 'position';
        campaign: string;
        position: string;
    }>({
        via: 'campaign',
        campaign: '',
        position: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            via: data.via,
            campaign: data.via === 'campaign' ? data.campaign : null,
            position: data.via === 'position' ? data.position : null,
        }));
        form.submit(exams.assignments.store(examId), {
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
                            {t('admin.programs.assignments.assign')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('admin.programs.assignments.assignDescription')}
                        </DialogDescription>
                    </DialogHeader>
                    <FormField
                        id="exam-via"
                        label={t('admin.programs.assignments.via')}
                        error={form.errors.via}
                    >
                        <OptionSelect
                            id="exam-via"
                            value={form.data.via}
                            onChange={(value) =>
                                form.setData(
                                    'via',
                                    (value ?? 'campaign') as
                                        | 'campaign'
                                        | 'position',
                                )
                            }
                            options={[
                                {
                                    value: 'campaign',
                                    label: t(
                                        'admin.programs.assignments.campaign',
                                    ),
                                },
                                {
                                    value: 'position',
                                    label: t(
                                        'admin.programs.assignments.position',
                                    ),
                                },
                            ]}
                        />
                    </FormField>
                    {form.data.via === 'campaign' ? (
                        <FormField
                            id="exam-campaign"
                            label={t('admin.programs.assignments.campaign')}
                            error={form.errors.campaign}
                        >
                            <OptionSelect
                                id="exam-campaign"
                                value={form.data.campaign || null}
                                onChange={(value) =>
                                    form.setData('campaign', value ?? '')
                                }
                                options={options.campaigns.map((campaign) => ({
                                    value: campaign.id,
                                    label: campaign.name,
                                }))}
                                placeholder={t(
                                    'admin.candidates.fields.campaignPlaceholder',
                                )}
                            />
                        </FormField>
                    ) : (
                        <FormField
                            id="exam-position"
                            label={t('admin.programs.assignments.position')}
                            error={form.errors.position}
                        >
                            <OptionSelect
                                id="exam-position"
                                value={form.data.position || null}
                                onChange={(value) =>
                                    form.setData('position', value ?? '')
                                }
                                options={options.positions.map((position) => ({
                                    value: position.id,
                                    label: `${position.name} — ${position.department}`,
                                }))}
                                placeholder={t(
                                    'admin.campaigns.fields.positionPlaceholder',
                                )}
                            />
                        </FormField>
                    )}
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {t('admin.programs.assignments.assign')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ScheduleDialog({
    row,
    onClose,
}: {
    row: ExamAssignmentRow;
    onClose: () => void;
}) {
    const form = useForm({
        opens_at_override: row.override.opens_at ?? '',
        closes_at_override: row.override.closes_at ?? '',
        max_attempts_override: row.override.max_attempts?.toString() ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            opens_at_override: data.opens_at_override || null,
            closes_at_override: data.closes_at_override || null,
            max_attempts_override: data.max_attempts_override || null,
        }));
        form.submit(examAssignments.update(row.id), {
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
                            {t('admin.exams.assignments.scheduleTitle')}
                        </DialogTitle>
                        <DialogDescription>
                            {row.candidate.name}.{' '}
                            {t('admin.exams.assignments.scheduleDescription')}
                        </DialogDescription>
                    </DialogHeader>
                    <FormField
                        id="override-opens"
                        label={t('admin.exams.assignments.opensAt')}
                        error={form.errors.opens_at_override}
                        optional
                    >
                        <Input
                            id="override-opens"
                            type="datetime-local"
                            value={form.data.opens_at_override}
                            onChange={(event) =>
                                form.setData(
                                    'opens_at_override',
                                    event.target.value,
                                )
                            }
                        />
                    </FormField>
                    <FormField
                        id="override-closes"
                        label={t('admin.exams.assignments.closesAt')}
                        error={form.errors.closes_at_override}
                        optional
                    >
                        <Input
                            id="override-closes"
                            type="datetime-local"
                            value={form.data.closes_at_override}
                            onChange={(event) =>
                                form.setData(
                                    'closes_at_override',
                                    event.target.value,
                                )
                            }
                        />
                    </FormField>
                    <FormField
                        id="override-attempts"
                        label={t('admin.exams.assignments.maxAttempts')}
                        error={form.errors.max_attempts_override}
                        optional
                    >
                        <Input
                            id="override-attempts"
                            type="number"
                            min={1}
                            max={10}
                            inputMode="numeric"
                            value={form.data.max_attempts_override}
                            onChange={(event) =>
                                form.setData(
                                    'max_attempts_override',
                                    event.target.value,
                                )
                            }
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
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
