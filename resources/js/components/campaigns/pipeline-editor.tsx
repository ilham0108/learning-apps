import { router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Check,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { t } from '@/lib/i18n';
import type { PipelineStage } from '@/types';
import type { RouteDefinition } from '@/wayfinder';

/** Route untuk satu alur: alur default atau alur satu rekrutmen. */
export type PipelineRoutes = {
    store: () => RouteDefinition<'post'>;
    update: (stageId: string) => RouteDefinition<'patch'>;
    move: (stageId: string) => RouteDefinition<'post'>;
    destroy: (stageId: string) => RouteDefinition<'delete'>;
};

type Props = {
    stages: PipelineStage[];
    routes: PipelineRoutes;
    editable: boolean;
    maxStages?: number;
};

function IconButton({
    label,
    onClick,
    disabled,
    children,
}: {
    label: string;
    onClick: () => void;
    disabled?: boolean;
    children: React.ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={label}
                    onClick={onClick}
                    disabled={disabled}
                >
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}

function RenameForm({
    stage,
    routes,
    onDone,
}: {
    stage: PipelineStage;
    routes: PipelineRoutes;
    onDone: () => void;
}) {
    const form = useForm({ name: stage.name });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(routes.update(stage.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-1 items-start gap-1">
            <div className="flex-1">
                <Input
                    aria-label={t('admin.pipeline.renameLabel')}
                    value={form.data.name}
                    maxLength={100}
                    required
                    autoFocus
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
                <InputError message={form.errors.name} />
            </div>
            <Button
                type="submit"
                variant="ghost"
                size="icon"
                aria-label={t('common.save')}
                disabled={form.processing}
            >
                {form.processing ? <Spinner /> : <Check aria-hidden="true" />}
            </Button>
            <IconButton label={t('common.cancel')} onClick={onDone}>
                <X aria-hidden="true" />
            </IconButton>
        </form>
    );
}

/**
 * Daftar tahap proses yang bisa ditambah, diganti nama, diurutkan, dan dihapus.
 * Tahap sistem di awal (Diundang → Terdaftar) dan akhir (Diterima) ditampilkan
 * tetapi tidak dapat diubah.
 */
export function PipelineEditor({
    stages,
    routes,
    editable,
    maxStages = 15,
}: Props) {
    const [renaming, setRenaming] = useState<string | null>(null);
    const [deleting, setDeleting] = useState<PipelineStage | null>(null);
    const [processing, setProcessing] = useState(false);
    const form = useForm({ name: '' });

    const add = (event: FormEvent) => {
        event.preventDefault();
        form.submit(routes.store(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const move = (stage: PipelineStage, direction: 'up' | 'down') =>
        router.visit(routes.move(stage.id), {
            data: { direction },
            preserveScroll: true,
        });

    return (
        <div className="space-y-3">
            <p className="text-xs text-muted-foreground">
                {t('admin.pipeline.fixedStart')}
            </p>

            {stages.length === 0 ? (
                <p className="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                    {t('admin.pipeline.empty')}
                </p>
            ) : (
                <ol className="divide-y rounded-lg border">
                    {stages.map((stage, index) => (
                        <li
                            key={stage.id}
                            className="flex flex-wrap items-center gap-2 p-2.5"
                        >
                            <span
                                className="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold tabular-nums"
                                aria-hidden="true"
                            >
                                {index + 1}
                            </span>
                            {renaming === stage.id ? (
                                <RenameForm
                                    stage={stage}
                                    routes={routes}
                                    onDone={() => setRenaming(null)}
                                />
                            ) : (
                                <>
                                    <span className="min-w-0 flex-1 text-sm font-medium">
                                        {stage.name}
                                    </span>
                                    {stage.current_count !== undefined &&
                                        stage.current_count > 0 && (
                                            <StatusBadge
                                                label={t(
                                                    'admin.pipeline.currentCount',
                                                    {
                                                        count: stage.current_count,
                                                    },
                                                )}
                                                tone="info"
                                            />
                                        )}
                                    {editable && (
                                        <div className="flex">
                                            <IconButton
                                                label={t(
                                                    'admin.pipeline.moveUp',
                                                    {
                                                        name: stage.name,
                                                    },
                                                )}
                                                onClick={() =>
                                                    move(stage, 'up')
                                                }
                                                disabled={index === 0}
                                            >
                                                <ArrowUp aria-hidden="true" />
                                            </IconButton>
                                            <IconButton
                                                label={t(
                                                    'admin.pipeline.moveDown',
                                                    { name: stage.name },
                                                )}
                                                onClick={() =>
                                                    move(stage, 'down')
                                                }
                                                disabled={
                                                    index === stages.length - 1
                                                }
                                            >
                                                <ArrowDown aria-hidden="true" />
                                            </IconButton>
                                            <IconButton
                                                label={t(
                                                    'admin.pipeline.rename',
                                                    {
                                                        name: stage.name,
                                                    },
                                                )}
                                                onClick={() =>
                                                    setRenaming(stage.id)
                                                }
                                            >
                                                <Pencil aria-hidden="true" />
                                            </IconButton>
                                            <IconButton
                                                label={
                                                    stage.removable === false
                                                        ? t(
                                                              'admin.pipeline.inUse',
                                                          )
                                                        : t(
                                                              'admin.pipeline.delete',
                                                              {
                                                                  name: stage.name,
                                                              },
                                                          )
                                                }
                                                onClick={() =>
                                                    setDeleting(stage)
                                                }
                                                disabled={
                                                    stage.removable === false
                                                }
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </IconButton>
                                        </div>
                                    )}
                                </>
                            )}
                        </li>
                    ))}
                </ol>
            )}

            <p className="text-xs text-muted-foreground">
                → {t('admin.pipeline.fixedEnd')}
            </p>

            {editable &&
                (stages.length < maxStages ? (
                    <form onSubmit={add} className="flex items-start gap-2">
                        <div className="flex-1">
                            <Input
                                aria-label={t('admin.pipeline.newLabel')}
                                placeholder={t('admin.pipeline.newPlaceholder')}
                                maxLength={100}
                                required
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={form.processing}
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <Plus aria-hidden="true" />
                            )}
                            {t('admin.pipeline.add')}
                        </Button>
                    </form>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        {t('admin.pipeline.max', { count: maxStages })}
                    </p>
                ))}

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={t('admin.pipeline.deleteTitle', {
                    name: deleting?.name ?? '',
                })}
                description={t('admin.pipeline.deleteDescription')}
                confirmLabel={t('common.delete')}
                destructive
                processing={processing}
                onConfirm={() =>
                    deleting &&
                    router.visit(routes.destroy(deleting.id), {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false);
                            setDeleting(null);
                        },
                    })
                }
            />
        </div>
    );
}
