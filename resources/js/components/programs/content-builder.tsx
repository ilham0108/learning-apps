import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    Pencil,
    Plus,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { EmptyState } from '@/components/app/empty-state';
import { FormField } from '@/components/app/form-field';
import { StatusBadge } from '@/components/app/status-badge';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { t } from '@/lib/i18n';
import { materialTypeIcons } from '@/lib/icons';
import { cn } from '@/lib/utils';
import materials from '@/routes/admin/materials';
import modules from '@/routes/admin/modules';
import type { AdminMaterial, ProgramDetail } from '@/types';

type Module = NonNullable<ProgramDetail['modules']>[number];

export function ContentBuilder({ program }: { program: ProgramDetail }) {
    const [moduleEditor, setModuleEditor] = useState<Module | 'new' | null>(
        null,
    );
    const items = program.modules ?? [];

    return (
        <section className="space-y-4" aria-labelledby="content-title">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h2 id="content-title" className="text-lg font-semibold">
                        {t('admin.programs.content.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('admin.programs.content.description')}
                    </p>
                </div>
                <Button onClick={() => setModuleEditor('new')}>
                    <Plus aria-hidden="true" />
                    {t('admin.programs.content.addModule')}
                </Button>
            </div>

            {items.length === 0 ? (
                <EmptyState
                    title={t('admin.programs.content.emptyModules')}
                    description={t(
                        'admin.programs.content.emptyModulesDescription',
                    )}
                />
            ) : (
                <ol className="space-y-4">
                    {items.map((module, index) => (
                        <ModuleCard
                            key={module.id}
                            module={module}
                            number={index + 1}
                            isFirst={index === 0}
                            isLast={index === items.length - 1}
                            onEdit={() => setModuleEditor(module)}
                        />
                    ))}
                </ol>
            )}

            {moduleEditor !== null && (
                <ModuleDialog
                    programId={program.id}
                    module={moduleEditor === 'new' ? null : moduleEditor}
                    onClose={() => setModuleEditor(null)}
                />
            )}
        </section>
    );
}

function ModuleCard({
    module,
    number,
    isFirst,
    isLast,
    onEdit,
}: {
    module: Module;
    number: number;
    isFirst: boolean;
    isLast: boolean;
    onEdit: () => void;
}) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const move = (direction: 'up' | 'down') =>
        router.visit(modules.move(module.id), {
            data: { direction },
            preserveScroll: true,
        });

    return (
        <li className="rounded-xl border">
            <div className="flex flex-wrap items-start justify-between gap-2 border-b p-4">
                <div className="space-y-1">
                    <h3 className="font-medium">
                        {number}. {module.title}
                    </h3>
                    {module.description && (
                        <p className="text-sm text-muted-foreground">
                            {module.description}
                        </p>
                    )}
                </div>
                <div className="flex flex-wrap gap-1">
                    <IconAction
                        label={t('admin.programs.content.moveUp')}
                        disabled={isFirst}
                        onClick={() => move('up')}
                    >
                        <ArrowUp aria-hidden="true" />
                    </IconAction>
                    <IconAction
                        label={t('admin.programs.content.moveDown')}
                        disabled={isLast}
                        onClick={() => move('down')}
                    >
                        <ArrowDown aria-hidden="true" />
                    </IconAction>
                    <IconAction
                        label={t('admin.programs.content.editModule')}
                        onClick={onEdit}
                    >
                        <Pencil aria-hidden="true" />
                    </IconAction>
                    <IconAction
                        label={t('admin.programs.content.deleteModule')}
                        disabled={module.materials.length > 0}
                        onClick={() => setConfirmDelete(true)}
                    >
                        <Trash2 aria-hidden="true" />
                    </IconAction>
                    <Button size="sm" variant="outline" asChild>
                        <Link href={materials.create(module.id)}>
                            <Plus aria-hidden="true" />
                            {t('admin.programs.content.addMaterial')}
                        </Link>
                    </Button>
                </div>
            </div>

            {module.materials.length === 0 ? (
                <p className="p-4 text-sm text-muted-foreground">
                    {t('admin.programs.content.emptyMaterials')}
                </p>
            ) : (
                <ul className="divide-y">
                    {module.materials.map((material, index) => (
                        <MaterialRow
                            key={material.id}
                            material={material}
                            isFirst={index === 0}
                            isLast={index === module.materials.length - 1}
                        />
                    ))}
                </ul>
            )}

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title={t('admin.programs.content.deleteModuleTitle')}
                description={t(
                    'admin.programs.content.deleteModuleDescription',
                )}
                confirmLabel={t('common.delete')}
                destructive
                onConfirm={() =>
                    router.visit(modules.destroy(module.id), {
                        preserveScroll: true,
                        onSuccess: () => setConfirmDelete(false),
                    })
                }
            />
        </li>
    );
}

function MaterialRow({
    material,
    isFirst,
    isLast,
}: {
    material: AdminMaterial;
    isFirst: boolean;
    isLast: boolean;
}) {
    const [confirmRemove, setConfirmRemove] = useState(false);
    const Icon = materialTypeIcons[material.type];
    const move = (direction: 'up' | 'down') =>
        router.visit(materials.move(material.id), {
            data: { direction },
            preserveScroll: true,
        });

    return (
        <li
            className={cn(
                'flex flex-wrap items-center justify-between gap-2 px-4 py-3',
                material.is_archived && 'opacity-60',
            )}
        >
            <div className="flex min-w-0 items-center gap-3">
                <Icon
                    className="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <div className="min-w-0">
                    <p className="truncate text-sm font-medium">
                        {material.title}
                    </p>
                    <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                        <span>{t(`status.materialType.${material.type}`)}</span>
                        <StatusBadge
                            label={
                                material.is_required
                                    ? t('candidate.learn.required')
                                    : t('candidate.learn.optional')
                            }
                            tone={material.is_required ? 'info' : 'neutral'}
                        />
                        {material.is_archived && (
                            <StatusBadge
                                label={t('admin.programs.content.archived')}
                                tone="warning"
                            />
                        )}
                    </div>
                </div>
            </div>
            <div className="flex flex-wrap gap-1">
                {material.preview_url && (
                    <IconAction
                        label={t('admin.programs.content.preview')}
                        href={material.preview_url}
                    >
                        <ExternalLink aria-hidden="true" />
                    </IconAction>
                )}
                <IconAction
                    label={t('admin.programs.content.moveUp')}
                    disabled={isFirst}
                    onClick={() => move('up')}
                >
                    <ArrowUp aria-hidden="true" />
                </IconAction>
                <IconAction
                    label={t('admin.programs.content.moveDown')}
                    disabled={isLast}
                    onClick={() => move('down')}
                >
                    <ArrowDown aria-hidden="true" />
                </IconAction>
                <IconAction
                    label={t('admin.programs.content.editMaterial')}
                    href={materials.edit(material.id).url}
                    internal
                >
                    <Pencil aria-hidden="true" />
                </IconAction>
                {material.is_archived ? (
                    <IconAction
                        label={t('admin.programs.content.restoreMaterial')}
                        onClick={() =>
                            router.visit(materials.restore(material.id), {
                                preserveScroll: true,
                            })
                        }
                    >
                        <RotateCcw aria-hidden="true" />
                    </IconAction>
                ) : (
                    <IconAction
                        label={t('admin.programs.content.removeMaterial')}
                        onClick={() => setConfirmRemove(true)}
                    >
                        <Trash2 aria-hidden="true" />
                    </IconAction>
                )}
            </div>

            <ConfirmDialog
                open={confirmRemove}
                onOpenChange={setConfirmRemove}
                title={t('admin.programs.content.removeMaterialTitle')}
                description={t(
                    'admin.programs.content.removeMaterialDescription',
                )}
                confirmLabel={t('common.delete')}
                destructive
                onConfirm={() =>
                    router.visit(materials.destroy(material.id), {
                        preserveScroll: true,
                        onSuccess: () => setConfirmRemove(false),
                    })
                }
            />
        </li>
    );
}

/**
 * Tombol ikon dengan aria-label dan tooltip (rules Bagian 12).
 */
function IconAction({
    label,
    onClick,
    href,
    internal = false,
    disabled = false,
    children,
}: {
    label: string;
    onClick?: () => void;
    href?: string;
    internal?: boolean;
    disabled?: boolean;
    children: ReactNode;
}) {
    const button = href ? (
        <Button
            variant="ghost"
            size="icon"
            className="size-8"
            asChild
            aria-label={label}
        >
            {internal ? (
                <Link href={href}>{children}</Link>
            ) : (
                <a href={href} target="_blank" rel="noopener noreferrer">
                    {children}
                </a>
            )}
        </Button>
    ) : (
        <Button
            variant="ghost"
            size="icon"
            className="size-8"
            aria-label={label}
            disabled={disabled}
            onClick={onClick}
        >
            {children}
        </Button>
    );

    return (
        <Tooltip>
            <TooltipTrigger asChild>{button}</TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}

function ModuleDialog({
    programId,
    module,
    onClose,
}: {
    programId: string;
    module: Module | null;
    onClose: () => void;
}) {
    const form = useForm({
        title: module?.title ?? '',
        description: module?.description ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(
            module ? modules.update(module.id) : modules.store(programId),
            {
                preserveScroll: true,
                onSuccess: onClose,
            },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-6">
                    <DialogHeader>
                        <DialogTitle>
                            {module
                                ? t('admin.programs.content.editModule')
                                : t('admin.programs.content.addModule')}
                        </DialogTitle>
                    </DialogHeader>
                    <FormField
                        id="module-title"
                        label={t('admin.programs.content.moduleTitle')}
                        error={form.errors.title}
                    >
                        <Input
                            id="module-title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            required
                            autoFocus
                        />
                    </FormField>
                    <FormField
                        id="module-description"
                        label={t('admin.programs.content.moduleDescription')}
                        error={form.errors.description}
                        optional
                    >
                        <Textarea
                            id="module-description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            maxLength={2000}
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
