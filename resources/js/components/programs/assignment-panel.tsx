import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Trash2, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import type { Column } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { EmptyState } from '@/components/app/empty-state';
import { FormField } from '@/components/app/form-field';
import { LearningStatusBadge } from '@/components/app/learning-badges';
import { OptionSelect } from '@/components/app/option-select';
import { ProgressBar } from '@/components/app/progress-bar';
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
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import candidates from '@/routes/admin/candidates';
import programAssignments from '@/routes/admin/program-assignments';
import programs from '@/routes/admin/programs';
import type {
    AssignmentSource,
    PositionOption,
    ProgramAssignmentRow,
    ProgramDetail,
} from '@/types';

export type AssignOptions = {
    campaigns: { id: string; name: string }[];
    positions: PositionOption[];
};

export function AssignmentPanel({
    program,
    assignments,
    options,
}: {
    program: ProgramDetail;
    assignments: ProgramAssignmentRow[];
    options: AssignOptions;
}) {
    const { displayTimezone } = usePage().props;
    const [assignOpen, setAssignOpen] = useState(false);
    const [revoking, setRevoking] = useState<ProgramAssignmentRow | null>(null);
    const isPublished = program.status === 'published';

    const columns: Column<ProgramAssignmentRow>[] = [
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
            key: 'via',
            header: t('admin.programs.assignments.columns.via'),
            cell: (row) => t(`status.assignmentSource.${row.assigned_via}`),
        },
        {
            key: 'due',
            header: t('admin.programs.assignments.columns.due'),
            className: 'whitespace-nowrap',
            cell: (row) => formatDate(row.due_at, displayTimezone),
        },
        {
            key: 'progress',
            header: t('admin.programs.assignments.columns.progress'),
            cell: (row) => (
                <div className="grid min-w-40 gap-1">
                    <LearningStatusBadge status={row.progress.status} />
                    <ProgressBar
                        percent={row.progress.percent}
                        label={t('candidate.learn.requiredProgress', {
                            done: row.progress.required_completed,
                            total: row.progress.required_total,
                        })}
                    />
                </div>
            ),
        },
        {
            key: 'actions',
            header: t('common.actions'),
            cell: (row) => (
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label={t('admin.programs.assignments.revoke')}
                    title={t('admin.programs.assignments.revoke')}
                    onClick={() => setRevoking(row)}
                >
                    <Trash2 aria-hidden="true" />
                </Button>
            ),
        },
    ];

    return (
        <section className="space-y-4" aria-labelledby="assignments-title">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h2
                        id="assignments-title"
                        className="text-lg font-semibold"
                    >
                        {t('admin.programs.assignments.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {isPublished
                            ? t('admin.programs.assignments.description')
                            : t('admin.programs.assignments.notPublished')}
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
                rows={assignments}
                caption={t('admin.programs.assignments.title')}
                empty={
                    <EmptyState title={t('admin.programs.assignments.empty')} />
                }
            />

            {assignOpen && (
                <AssignDialog
                    program={program}
                    options={options}
                    onClose={() => setAssignOpen(false)}
                />
            )}

            <ConfirmDialog
                open={revoking !== null}
                onOpenChange={(open) => !open && setRevoking(null)}
                title={t('admin.programs.assignments.revokeTitle')}
                description={t('admin.programs.assignments.revokeDescription')}
                confirmLabel={t('admin.programs.assignments.revoke')}
                destructive
                onConfirm={() =>
                    revoking &&
                    router.visit(programAssignments.destroy(revoking.id), {
                        preserveScroll: true,
                        onSuccess: () => setRevoking(null),
                    })
                }
            />
        </section>
    );
}

function AssignDialog({
    program,
    options,
    onClose,
}: {
    program: ProgramDetail;
    options: AssignOptions;
    onClose: () => void;
}) {
    const form = useForm<{
        via: AssignmentSource;
        campaign: string;
        position: string;
        due_at: string;
    }>({
        via: 'campaign',
        campaign: '',
        position: '',
        due_at: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            via: data.via,
            campaign: data.via === 'campaign' ? data.campaign : null,
            position: data.via === 'position' ? data.position : null,
            due_at: data.due_at || null,
        }));
        form.submit(programs.assignments.store(program.id), {
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
                            {t('admin.programs.assignments.assignTitle')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('admin.programs.assignments.assignDescription')}
                        </DialogDescription>
                    </DialogHeader>

                    <FormField
                        id="via"
                        label={t('admin.programs.assignments.via')}
                        error={form.errors.via}
                    >
                        <OptionSelect
                            id="via"
                            value={form.data.via}
                            onChange={(value) =>
                                form.setData(
                                    'via',
                                    (value ?? 'campaign') as AssignmentSource,
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
                            id="campaign"
                            label={t('admin.programs.assignments.campaign')}
                            error={form.errors.campaign}
                        >
                            <OptionSelect
                                id="campaign"
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
                            id="position"
                            label={t('admin.programs.assignments.position')}
                            error={form.errors.position}
                        >
                            <OptionSelect
                                id="position"
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

                    <p className="text-xs text-muted-foreground">
                        {t('admin.programs.assignments.candidatesHelp')}
                    </p>

                    <FormField
                        id="due_at"
                        label={t('admin.programs.assignments.dueAt')}
                        error={form.errors.due_at}
                        optional
                    >
                        <Input
                            id="due_at"
                            type="date"
                            value={form.data.due_at}
                            onChange={(event) =>
                                form.setData('due_at', event.target.value)
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
                            {t('admin.programs.assignments.assign')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
