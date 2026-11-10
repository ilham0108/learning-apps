import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { FormField } from '@/components/app/form-field';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { t } from '@/lib/i18n';
import { PositionIcon } from '@/lib/icons';
import departments from '@/routes/admin/departments';
import positions from '@/routes/admin/positions';
import type { Department } from '@/types';

type Props = {
    departments: Department[];
};

type EditorState =
    | { kind: 'department'; department?: Department }
    | {
          kind: 'position';
          department: Department;
          position?: Department['positions'][number];
      }
    | null;

export default function PositionsIndex({ departments: items }: Props) {
    const [editor, setEditor] = useState<EditorState>(null);

    return (
        <>
            <Head title={t('admin.positions.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.positions.title')}
                    description={t('admin.positions.description')}
                    actions={
                        <Button
                            onClick={() => setEditor({ kind: 'department' })}
                        >
                            <Plus aria-hidden="true" />
                            {t('admin.positions.addDepartment')}
                        </Button>
                    }
                />

                {items.length === 0 ? (
                    <EmptyState
                        title={t('admin.positions.empty')}
                        description={t('admin.positions.emptyDescription')}
                    />
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {items.map((department) => (
                            <Card key={department.id}>
                                <CardHeader className="flex flex-row items-center justify-between gap-2">
                                    <CardTitle className="flex items-center gap-2">
                                        <PositionIcon
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                        {department.name}
                                        {!department.is_active && (
                                            <StatusBadge
                                                label={t('common.inactive')}
                                            />
                                        )}
                                    </CardTitle>
                                    <div className="flex gap-1">
                                        <IconButton
                                            label={t(
                                                'admin.positions.editDepartment',
                                            )}
                                            onClick={() =>
                                                setEditor({
                                                    kind: 'department',
                                                    department,
                                                })
                                            }
                                        >
                                            <Pencil aria-hidden="true" />
                                        </IconButton>
                                        <IconButton
                                            label={t(
                                                'admin.positions.addPosition',
                                            )}
                                            onClick={() =>
                                                setEditor({
                                                    kind: 'position',
                                                    department,
                                                })
                                            }
                                        >
                                            <Plus aria-hidden="true" />
                                        </IconButton>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {department.positions.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            {t('admin.positions.noPositions')}
                                        </p>
                                    ) : (
                                        <ul className="divide-y">
                                            {department.positions.map(
                                                (position) => (
                                                    <li
                                                        key={position.id}
                                                        className="flex items-center justify-between gap-2 py-2"
                                                    >
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span>
                                                                {position.name}
                                                            </span>
                                                            {!position.is_active && (
                                                                <StatusBadge
                                                                    label={t(
                                                                        'common.inactive',
                                                                    )}
                                                                />
                                                            )}
                                                            <span className="text-xs text-muted-foreground">
                                                                {t(
                                                                    'admin.positions.campaignsUsing',
                                                                    {
                                                                        count: position.campaigns_count,
                                                                    },
                                                                )}
                                                            </span>
                                                        </div>
                                                        <IconButton
                                                            label={t(
                                                                'admin.positions.editPosition',
                                                            )}
                                                            onClick={() =>
                                                                setEditor({
                                                                    kind: 'position',
                                                                    department,
                                                                    position,
                                                                })
                                                            }
                                                        >
                                                            <Pencil aria-hidden="true" />
                                                        </IconButton>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {editor && (
                <EditorDialog editor={editor} onClose={() => setEditor(null)} />
            )}
        </>
    );
}

function IconButton({
    label,
    onClick,
    children,
}: {
    label: string;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={label}
                    onClick={onClick}
                >
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}

function EditorDialog({
    editor,
    onClose,
}: {
    editor: NonNullable<EditorState>;
    onClose: () => void;
}) {
    const existing =
        editor.kind === 'department' ? editor.department : editor.position;
    const form = useForm({
        name: existing?.name ?? '',
        is_active: existing?.is_active ?? true,
        department:
            editor.kind === 'position' && !editor.position
                ? editor.department.id
                : undefined,
    });

    const title =
        editor.kind === 'department'
            ? existing
                ? t('admin.positions.editDepartment')
                : t('admin.positions.addDepartment')
            : existing
              ? t('admin.positions.editPosition')
              : t('admin.positions.addPosition');

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const target =
            editor.kind === 'department'
                ? editor.department
                    ? departments.update(editor.department.id)
                    : departments.store()
                : editor.position
                  ? positions.update(editor.position.id)
                  : positions.store();

        form.submit(target, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-6">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {editor.kind === 'position' && (
                            <DialogDescription>
                                {editor.department.name}
                            </DialogDescription>
                        )}
                    </DialogHeader>

                    <FormField
                        id="name"
                        label={
                            editor.kind === 'department'
                                ? t('admin.positions.departmentName')
                                : t('admin.positions.positionName')
                        }
                        error={form.errors.name ?? form.errors.department}
                    >
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                            autoFocus
                        />
                    </FormField>

                    {existing && (
                        <div className="grid gap-2">
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_active',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="is_active">
                                    {t('common.active')}
                                </Label>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {t('admin.positions.activeHelp')}
                            </p>
                        </div>
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
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
