import { router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import applications from '@/routes/admin/applications';
import notes from '@/routes/admin/notes';
import type { ApplicationDetail, InternalNote } from '@/types';

export function NotesSection({
    application,
}: {
    application: ApplicationDetail;
}) {
    const form = useForm({ body: '' });
    const fieldId = `note-${application.id}`;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(applications.notes.store(application.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <section className="space-y-4" aria-labelledby={`${fieldId}-title`}>
            <div className="space-y-1">
                <h3 id={`${fieldId}-title`} className="text-sm font-medium">
                    {t('admin.applications.notes.title')}
                </h3>
                <p className="text-xs text-muted-foreground">
                    {t('admin.applications.notes.description')}
                </p>
            </div>

            <form onSubmit={submit} className="grid gap-2">
                <Label htmlFor={fieldId} className="sr-only">
                    {t('admin.applications.notes.title')}
                </Label>
                <Textarea
                    id={fieldId}
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                    placeholder={t('admin.applications.notes.placeholder')}
                    maxLength={5000}
                />
                <InputError message={form.errors.body} />
                <div>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={
                            form.processing || form.data.body.trim() === ''
                        }
                    >
                        {form.processing && <Spinner />}
                        {t('admin.applications.notes.add')}
                    </Button>
                </div>
            </form>

            {application.notes.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('admin.applications.notes.empty')}
                </p>
            ) : (
                <ul className="space-y-3">
                    {application.notes.map((note) => (
                        <NoteItem key={note.id} note={note} />
                    ))}
                </ul>
            )}
        </section>
    );
}

function NoteItem({ note }: { note: InternalNote }) {
    const { displayTimezone } = usePage().props;
    const [editing, setEditing] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const form = useForm({ body: note.body });

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.submit(notes.update(note.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const remove = () =>
        router.visit(notes.destroy(note.id), {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
            onSuccess: () => setConfirmDelete(false),
        });

    return (
        <li className="rounded-lg border p-3 text-sm">
            <div className="flex items-start justify-between gap-2">
                <p className="text-xs text-muted-foreground">
                    {note.author ?? t('common.notAvailable')} ·{' '}
                    {formatDateTime(note.created_at, displayTimezone)}
                    {note.updated_at !== note.created_at &&
                        ` ${t('admin.applications.notes.edited')}`}
                </p>
                {note.can_edit && !editing && (
                    <div className="flex gap-1">
                        <ActionButton
                            label={t('admin.applications.notes.editLabel')}
                            onClick={() => setEditing(true)}
                        >
                            <Pencil aria-hidden="true" />
                        </ActionButton>
                        <ActionButton
                            label={t('admin.applications.notes.deleteLabel')}
                            onClick={() => setConfirmDelete(true)}
                        >
                            <Trash2 aria-hidden="true" />
                        </ActionButton>
                    </div>
                )}
            </div>

            {editing ? (
                <form onSubmit={save} className="mt-2 grid gap-2">
                    <Label htmlFor={`edit-${note.id}`} className="sr-only">
                        {t('admin.applications.notes.editLabel')}
                    </Label>
                    <Textarea
                        id={`edit-${note.id}`}
                        value={form.data.body}
                        onChange={(event) =>
                            form.setData('body', event.target.value)
                        }
                        maxLength={5000}
                        autoFocus
                    />
                    <InputError message={form.errors.body} />
                    <div className="flex gap-2">
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            {t('common.save')}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setEditing(false)}
                        >
                            {t('common.cancel')}
                        </Button>
                    </div>
                </form>
            ) : (
                <p className="mt-1 whitespace-pre-line">{note.body}</p>
            )}

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title={t('admin.applications.notes.deleteTitle')}
                description={t('admin.applications.notes.deleteDescription')}
                confirmLabel={t('common.delete')}
                destructive
                processing={deleting}
                onConfirm={remove}
            />
        </li>
    );
}

function ActionButton({
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
                    className="size-8"
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
