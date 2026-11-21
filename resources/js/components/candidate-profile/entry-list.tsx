import { router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { t } from '@/lib/i18n';
import type { RouteDefinition } from '@/wayfinder';

type Props<Entry extends { id: string }> = {
    entries: Entry[];
    empty: string;
    addLabel: string;
    deleteTitle: string;
    /** Judul baris untuk label tombol aksi dan pembaca layar. */
    title: (entry: Entry) => string;
    render: (entry: Entry) => ReactNode;
    destroy: (entry: Entry) => RouteDefinition<'delete'>;
    /** Dialog form; `entry` kosong berarti tambah baru. */
    form: (props: {
        entry: Entry | null;
        open: boolean;
        onOpenChange: (open: boolean) => void;
    }) => ReactNode;
};

/**
 * Daftar entri yang bisa ditambah, diubah, dan dihapus (riwayat pendidikan,
 * pengalaman kerja). Tombol ikon memakai label dan tooltip.
 */
export function EntryList<Entry extends { id: string }>({
    entries,
    empty,
    addLabel,
    deleteTitle,
    title,
    render,
    destroy,
    form,
}: Props<Entry>) {
    const [editing, setEditing] = useState<Entry | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Entry | null>(null);
    const [processing, setProcessing] = useState(false);

    const openForm = (entry: Entry | null) => {
        setEditing(entry);
        setFormOpen(true);
    };

    return (
        <div className="space-y-3">
            {entries.length === 0 ? (
                <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    {empty}
                </p>
            ) : (
                <ul className="divide-y rounded-lg border">
                    {entries.map((entry) => (
                        <li
                            key={entry.id}
                            className="flex items-start justify-between gap-3 p-4"
                        >
                            <div className="min-w-0 flex-1">
                                {render(entry)}
                            </div>
                            <div className="flex shrink-0 gap-1">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`${t('common.edit')}: ${title(entry)}`}
                                            onClick={() => openForm(entry)}
                                        >
                                            <Pencil aria-hidden="true" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {t('common.edit')}
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`${t('common.delete')}: ${title(entry)}`}
                                            onClick={() => setDeleting(entry)}
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {t('common.delete')}
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <Button variant="outline" onClick={() => openForm(null)}>
                <Plus aria-hidden="true" />
                {addLabel}
            </Button>

            {form({
                entry: editing,
                open: formOpen,
                onOpenChange: setFormOpen,
            })}

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={deleteTitle}
                description={deleting ? title(deleting) : undefined}
                confirmLabel={t('common.delete')}
                destructive
                processing={processing}
                onConfirm={() =>
                    deleting &&
                    router.visit(destroy(deleting), {
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
