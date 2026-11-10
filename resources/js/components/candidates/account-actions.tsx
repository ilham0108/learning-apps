import { router, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, KeyRound, Pencil, UserCheck, UserX } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { FormField } from '@/components/app/form-field';
import { CandidateFormDialog } from '@/components/candidates/candidate-form-dialog';
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
import { Textarea } from '@/components/ui/textarea';
import { useClipboard } from '@/hooks/use-clipboard';
import { t } from '@/lib/i18n';
import candidates from '@/routes/admin/candidates';
import type { CandidateDetail } from '@/types';

/**
 * Ubah data, buat tautan aktivasi, dan nonaktifkan/aktifkan akun kandidat.
 */
export function AccountActions({ candidate }: { candidate: CandidateDetail }) {
    const { flash } = usePage();
    const [activationLink, setActivationLink] = useState<string | null>(null);
    const [statusOpen, setStatusOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [requesting, setRequesting] = useState(false);

    // Tautan hanya dikirim sekali lewat flash; simpan di state agar dialog bisa ditutup.
    useEffect(() => {
        if (flash.activationLink) {
            setActivationLink(flash.activationLink);
        }
    }, [flash.activationLink]);

    const requestLink = () =>
        router.visit(candidates.activationLink.store(candidate.id), {
            preserveScroll: true,
            onStart: () => setRequesting(true),
            onFinish: () => setRequesting(false),
        });

    return (
        <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => setEditOpen(true)}>
                <Pencil aria-hidden="true" />
                {t('common.edit')}
            </Button>

            {candidate.is_active && candidate.activated_at === null && (
                <Button
                    variant="outline"
                    onClick={requestLink}
                    disabled={requesting}
                >
                    {requesting ? <Spinner /> : <KeyRound aria-hidden="true" />}
                    {t('admin.candidates.activation.button')}
                </Button>
            )}

            <Button
                variant={candidate.is_active ? 'destructive' : 'outline'}
                onClick={() => setStatusOpen(true)}
            >
                {candidate.is_active ? (
                    <UserX aria-hidden="true" />
                ) : (
                    <UserCheck aria-hidden="true" />
                )}
                {candidate.is_active
                    ? t('admin.candidates.status.deactivate')
                    : t('admin.candidates.status.activate')}
            </Button>

            {activationLink && (
                <ActivationLinkDialog
                    link={activationLink}
                    onClose={() => setActivationLink(null)}
                />
            )}

            {editOpen && (
                <CandidateFormDialog
                    mode="edit"
                    candidate={candidate}
                    onClose={() => setEditOpen(false)}
                />
            )}

            {statusOpen && (
                <StatusDialog
                    candidate={candidate}
                    onClose={() => setStatusOpen(false)}
                />
            )}
        </div>
    );
}

function ActivationLinkDialog({
    link,
    onClose,
}: {
    link: string;
    onClose: () => void;
}) {
    const [copiedText, copy] = useClipboard();
    const copied = copiedText === link;

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('admin.candidates.activation.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('admin.candidates.activation.description')}
                    </DialogDescription>
                </DialogHeader>
                <div className="flex gap-2">
                    <Input
                        value={link}
                        readOnly
                        aria-label={t('admin.candidates.activation.title')}
                        onFocus={(event) => event.target.select()}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => copy(link)}
                    >
                        {copied ? (
                            <Check aria-hidden="true" />
                        ) : (
                            <Copy aria-hidden="true" />
                        )}
                        {copied
                            ? t('admin.candidates.activation.copied')
                            : t('admin.candidates.activation.copy')}
                    </Button>
                </div>
                <DialogFooter>
                    <Button onClick={onClose}>{t('common.close')}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function StatusDialog({
    candidate,
    onClose,
}: {
    candidate: CandidateDetail;
    onClose: () => void;
}) {
    const deactivating = candidate.is_active;
    const form = useForm({ active: !deactivating, reason: '' });

    return (
        <ConfirmDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={
                deactivating
                    ? t('admin.candidates.status.deactivateTitle')
                    : t('admin.candidates.status.activateTitle')
            }
            description={
                deactivating
                    ? t('admin.candidates.status.deactivateDescription')
                    : t('admin.candidates.status.activateDescription')
            }
            confirmLabel={
                deactivating
                    ? t('admin.candidates.status.deactivate')
                    : t('admin.candidates.status.activate')
            }
            destructive={deactivating}
            processing={form.processing}
            onConfirm={() =>
                form.submit(candidates.status.update(candidate.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                })
            }
        >
            {deactivating && (
                <FormField
                    id="status-reason"
                    label={t('common.reason')}
                    error={form.errors.reason}
                >
                    <Textarea
                        id="status-reason"
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        placeholder={t(
                            'admin.candidates.status.reasonPlaceholder',
                        )}
                        maxLength={1000}
                    />
                </FormField>
            )}
        </ConfirmDialog>
    );
}
