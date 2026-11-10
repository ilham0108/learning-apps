import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/app/form-field';
import { OptionSelect } from '@/components/app/option-select';
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
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import programs from '@/routes/admin/programs';
import type { PositionOption, ProgramDetail, ProgramStatus } from '@/types';

const statuses: ProgramStatus[] = ['draft', 'published', 'archived'];

/**
 * Modal buat/ubah informasi program. Setelah dibuat, server mengarahkan ke halaman
 * program untuk menyusun modul dan materi.
 */
export function ProgramFormDialog({
    program,
    positions,
    onClose,
}: {
    program: ProgramDetail | null;
    positions: PositionOption[];
    onClose: () => void;
}) {
    const form = useForm({
        name: program?.name ?? '',
        description: program?.description ?? '',
        category: program?.category ?? '',
        target_position: program?.target_position?.id ?? '',
        status: program?.status ?? ('draft' as ProgramStatus),
        estimated_minutes: program?.estimated_minutes?.toString() ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(program ? programs.update(program.id) : programs.store(), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit} className="grid gap-6">
                    <DialogHeader>
                        <DialogTitle>
                            {program
                                ? t('admin.programs.editTitle')
                                : t('admin.programs.createTitle')}
                        </DialogTitle>
                    </DialogHeader>

                    <FormField
                        id="program-name"
                        label={t('admin.programs.fields.name')}
                        error={form.errors.name}
                    >
                        <Input
                            id="program-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                            autoFocus
                        />
                    </FormField>

                    <FormField
                        id="program-description"
                        label={t('admin.programs.fields.description')}
                        error={form.errors.description}
                        optional
                    >
                        <Textarea
                            id="program-description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            maxLength={5000}
                        />
                    </FormField>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            id="program-category"
                            label={t('admin.programs.fields.category')}
                            error={form.errors.category}
                            optional
                        >
                            <Input
                                id="program-category"
                                value={form.data.category}
                                onChange={(event) =>
                                    form.setData('category', event.target.value)
                                }
                                placeholder={t(
                                    'admin.programs.fields.categoryPlaceholder',
                                )}
                            />
                        </FormField>
                        <FormField
                            id="program-minutes"
                            label={t('admin.programs.fields.estimatedMinutes')}
                            error={form.errors.estimated_minutes}
                            optional
                        >
                            <Input
                                id="program-minutes"
                                type="number"
                                min={1}
                                inputMode="numeric"
                                value={form.data.estimated_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'estimated_minutes',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </div>

                    <FormField
                        id="program-target"
                        label={t('admin.programs.fields.targetPosition')}
                        error={form.errors.target_position}
                        optional
                    >
                        <OptionSelect
                            id="program-target"
                            value={form.data.target_position || null}
                            onChange={(value) =>
                                form.setData('target_position', value ?? '')
                            }
                            options={positions.map((position) => ({
                                value: position.id,
                                label: `${position.name} — ${position.department}`,
                            }))}
                            allowAll
                        />
                    </FormField>

                    <FormField
                        id="program-status"
                        label={t('admin.programs.fields.status')}
                        error={form.errors.status}
                        description={t('admin.programs.statusHelp')}
                    >
                        <OptionSelect
                            id="program-status"
                            value={form.data.status}
                            onChange={(value) =>
                                form.setData(
                                    'status',
                                    (value ?? 'draft') as ProgramStatus,
                                )
                            }
                            options={statuses.map((status) => ({
                                value: status,
                                label: t(`status.program.${status}`),
                            }))}
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
