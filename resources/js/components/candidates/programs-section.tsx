import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { FormField } from '@/components/app/form-field';
import { LearningStatusBadge } from '@/components/app/learning-badges';
import { OptionSelect } from '@/components/app/option-select';
import { ProgressBar } from '@/components/app/progress-bar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import programs from '@/routes/admin/programs';
import type { ApplicationDetail, ProgressSummary } from '@/types';

export type CandidateProgramAssignment = {
    id: string;
    program: { id: string; name: string };
    due_at: string | null;
    progress: ProgressSummary;
};

export type ProgramOption = { id: string; name: string };

export function ProgramsSection({
    application,
    assignments,
    programOptions,
}: {
    application: ApplicationDetail;
    assignments: CandidateProgramAssignment[];
    programOptions: ProgramOption[];
}) {
    const [open, setOpen] = useState(false);
    const assigned = new Set(
        assignments.map((assignment) => assignment.program.id),
    );
    const available = programOptions.filter(
        (program) => !assigned.has(program.id),
    );

    return (
        <section
            className="space-y-3"
            aria-labelledby={`programs-${application.id}`}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3
                    id={`programs-${application.id}`}
                    className="text-sm font-medium"
                >
                    {t('nav.programs')}
                </h3>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setOpen(true)}
                    disabled={available.length === 0}
                >
                    {t('admin.programs.assignments.assign')}
                </Button>
            </div>

            {assignments.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('admin.programs.assignments.empty')}
                </p>
            ) : (
                <ul className="grid gap-3">
                    {assignments.map((assignment) => (
                        <li
                            key={assignment.id}
                            className="grid gap-2 rounded-lg border p-3"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-sm font-medium">
                                    {assignment.program.name}
                                </span>
                                <LearningStatusBadge
                                    status={assignment.progress.status}
                                />
                            </div>
                            <ProgressBar
                                percent={assignment.progress.percent}
                                label={t('candidate.learn.requiredProgress', {
                                    done: assignment.progress
                                        .required_completed,
                                    total: assignment.progress.required_total,
                                })}
                            />
                        </li>
                    ))}
                </ul>
            )}

            {open && (
                <AssignDialog
                    application={application}
                    programs={available}
                    onClose={() => setOpen(false)}
                />
            )}
        </section>
    );
}

function AssignDialog({
    application,
    programs: options,
    onClose,
}: {
    application: ApplicationDetail;
    programs: ProgramOption[];
    onClose: () => void;
}) {
    const form = useForm({ program: '', due_at: '' });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            via: 'individual',
            applications: [application.id],
            due_at: data.due_at || null,
        }));
        form.submit(programs.assignments.store(form.data.program), {
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
                    </DialogHeader>
                    <FormField
                        id={`program-${application.id}`}
                        label={t('nav.programs')}
                        error={errors.applications}
                    >
                        <OptionSelect
                            id={`program-${application.id}`}
                            value={form.data.program || null}
                            onChange={(value) =>
                                form.setData('program', value ?? '')
                            }
                            options={options.map((program) => ({
                                value: program.id,
                                label: program.name,
                            }))}
                        />
                    </FormField>
                    <FormField
                        id={`due-${application.id}`}
                        label={t('admin.programs.assignments.dueAt')}
                        error={form.errors.due_at}
                        optional
                    >
                        <Input
                            id={`due-${application.id}`}
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
                        <Button
                            type="submit"
                            disabled={
                                form.processing || form.data.program === ''
                            }
                        >
                            {form.processing && <Spinner />}
                            {t('admin.programs.assignments.assign')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
