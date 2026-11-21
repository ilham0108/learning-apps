import { router, useForm, usePage } from '@inertiajs/react';
import { ExternalLink, Trash2, Upload } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';
import { useId, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { FormField } from '@/components/app/form-field';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { CompletedIcon, MaterialIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';
import candidateProfile from '@/routes/candidate-profile';
import type { CandidateDocument, CandidateDocumentType } from '@/types';

const MAX_BYTES = 5 * 1024 * 1024;
const MAX_CERTIFICATES = 5;

const accept: Record<CandidateDocumentType, string> = {
    cv: 'application/pdf,.pdf',
    ktp: 'application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png',
    photo: 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp',
    certificate: 'application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png',
};

const formatSize = (bytes: number) =>
    bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;

/** Pemeriksaan ukuran di klien hanya untuk kenyamanan; server tetap memvalidasi. */
function withinLimit(file: File): boolean {
    if (file.size > MAX_BYTES) {
        toast.error(t('candidate.profile.documents.tooLarge'));

        return false;
    }

    return true;
}

function DocumentActions({
    document,
    onDelete,
}: {
    document: CandidateDocument;
    onDelete: () => void;
}) {
    return (
        <>
            <Button asChild variant="ghost" size="sm">
                <a
                    href={document.url}
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <ExternalLink aria-hidden="true" />
                    {t('candidate.profile.documents.view')}
                    <span className="sr-only">: {document.name}</span>
                </a>
            </Button>
            <Button
                variant="ghost"
                size="sm"
                onClick={onDelete}
                aria-label={`${t('common.delete')}: ${document.label ?? document.name}`}
            >
                <Trash2 aria-hidden="true" />
            </Button>
        </>
    );
}

function DocumentMeta({ document }: { document: CandidateDocument }) {
    const { displayTimezone } = usePage().props;

    return (
        <p className="truncate text-xs text-muted-foreground">
            {document.name} · {formatSize(document.size)}
            {document.uploaded_at &&
                ` · ${t('candidate.profile.documents.uploadedAt', {
                    date: formatDate(document.uploaded_at, displayTimezone),
                })}`}
        </p>
    );
}

/** Satu slot dokumen tunggal (CV, KTP, foto): unggah, ganti, lihat, hapus. */
function SingleDocument({
    type,
    document,
    onDelete,
}: {
    type: Exclude<CandidateDocumentType, 'certificate'>;
    document: CandidateDocument | undefined;
    onDelete: (document: CandidateDocument) => void;
}) {
    const inputId = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const upload = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file || !withinLimit(file)) {
            return;
        }

        router.post(
            candidateProfile.documents.store().url,
            { type, file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onFinish: () => setUploading(false),
                onError: (errors) =>
                    toast.error(errors.file ?? errors.type ?? ''),
            },
        );
    };

    return (
        <li
            className={cn(
                'flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center',
                !document && 'border-dashed',
            )}
        >
            {type === 'photo' && document ? (
                <img
                    src={document.url}
                    alt={t('candidate.profile.documents.types.photo.label')}
                    className="size-14 shrink-0 rounded-full border object-cover"
                />
            ) : (
                <div className="flex size-14 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <MaterialIcon className="size-6" aria-hidden="true" />
                </div>
            )}
            <div className="min-w-0 flex-1 space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="font-medium">
                        {t(`candidate.profile.documents.types.${type}.label`)}
                    </p>
                    {document ? (
                        <span className="inline-flex items-center gap-1 text-xs text-green-700 dark:text-green-400">
                            <CompletedIcon
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            {t('candidate.profile.completeness.done')}
                        </span>
                    ) : (
                        <StatusBadge
                            label={t('candidate.profile.required')}
                            tone="warning"
                        />
                    )}
                </div>
                {document ? (
                    <DocumentMeta document={document} />
                ) : (
                    <p className="text-xs text-muted-foreground">
                        {t(`candidate.profile.documents.types.${type}.help`)}
                    </p>
                )}
            </div>
            <div className="flex flex-wrap items-center gap-1">
                {document && (
                    <DocumentActions
                        document={document}
                        onDelete={() => onDelete(document)}
                    />
                )}
                <input
                    ref={inputRef}
                    id={inputId}
                    type="file"
                    accept={accept[type]}
                    className="sr-only"
                    tabIndex={-1}
                    onChange={upload}
                />
                <Button
                    size="sm"
                    variant={document ? 'outline' : 'default'}
                    disabled={uploading}
                    onClick={() => inputRef.current?.click()}
                >
                    {uploading ? <Spinner /> : <Upload aria-hidden="true" />}
                    {document
                        ? t('candidate.profile.documents.replace')
                        : t('candidate.profile.documents.upload')}
                    <span className="sr-only">
                        {' '}
                        {t(`candidate.profile.documents.types.${type}.label`)}
                    </span>
                </Button>
            </div>
        </li>
    );
}

function CertificateForm() {
    const form = useForm<{
        type: 'certificate';
        label: string;
        file: File | null;
    }>({
        type: 'certificate',
        label: '',
        file: null,
    });
    const fileRef = useRef<HTMLInputElement>(null);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (form.data.file && !withinLimit(form.data.file)) {
            return;
        }

        form.post(candidateProfile.documents.store().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();

                if (fileRef.current) {
                    fileRef.current.value = '';
                }
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 rounded-lg border border-dashed p-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end"
        >
            <FormField
                id="certificate-label"
                label={t('candidate.profile.documents.certificateName')}
                error={form.errors.label}
            >
                <Input
                    id="certificate-label"
                    required
                    maxLength={150}
                    placeholder={t(
                        'candidate.profile.documents.certificatePlaceholder',
                    )}
                    value={form.data.label}
                    onChange={(event) =>
                        form.setData('label', event.target.value)
                    }
                />
            </FormField>
            <FormField
                id="certificate-file"
                label={t('candidate.profile.documents.types.certificate.label')}
                error={form.errors.file}
            >
                <Input
                    id="certificate-file"
                    ref={fileRef}
                    type="file"
                    required
                    accept={accept.certificate}
                    onChange={(event) =>
                        form.setData('file', event.target.files?.[0] ?? null)
                    }
                />
            </FormField>
            <Button type="submit" disabled={form.processing}>
                {form.processing ? <Spinner /> : <Upload aria-hidden="true" />}
                {t('candidate.profile.documents.addCertificate')}
            </Button>
        </form>
    );
}

export function DocumentsSection({
    documents,
}: {
    documents: CandidateDocument[];
}) {
    const [deleting, setDeleting] = useState<CandidateDocument | null>(null);
    const [processing, setProcessing] = useState(false);
    const single = (type: CandidateDocumentType) =>
        documents.find((document) => document.type === type);
    const certificates = documents.filter(
        (document) => document.type === 'certificate',
    );

    return (
        <div className="space-y-6">
            <ul className="space-y-3">
                {(['cv', 'ktp', 'photo'] as const).map((type) => (
                    <SingleDocument
                        key={type}
                        type={type}
                        document={single(type)}
                        onDelete={setDeleting}
                    />
                ))}
            </ul>

            <div className="space-y-3">
                <div>
                    <h3 className="font-medium">
                        {t(
                            'candidate.profile.documents.types.certificate.label',
                        )}{' '}
                        <span className="text-sm font-normal text-muted-foreground">
                            ({t('candidate.profile.optional')})
                        </span>
                    </h3>
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'candidate.profile.documents.types.certificate.help',
                        )}
                    </p>
                </div>
                {certificates.length > 0 && (
                    <ul className="divide-y rounded-lg border">
                        {certificates.map((document) => (
                            <li
                                key={document.id}
                                className="flex flex-wrap items-center gap-2 p-3"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {document.label}
                                    </p>
                                    <DocumentMeta document={document} />
                                </div>
                                <DocumentActions
                                    document={document}
                                    onDelete={() => setDeleting(document)}
                                />
                            </li>
                        ))}
                    </ul>
                )}
                {certificates.length < MAX_CERTIFICATES ? (
                    <CertificateForm />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {t('candidate.profile.documents.maxCertificates')}
                    </p>
                )}
            </div>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={t('candidate.profile.documents.deleteTitle')}
                description={t('candidate.profile.documents.deleteDescription')}
                confirmLabel={t('common.delete')}
                destructive
                processing={processing}
                onConfirm={() =>
                    deleting &&
                    router.visit(
                        candidateProfile.documents.destroy(deleting.id),
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: () => {
                                setProcessing(false);
                                setDeleting(null);
                            },
                        },
                    )
                }
            />
        </div>
    );
}
