import { useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/app/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import questionImports from '@/routes/admin/question-imports';

/**
 * Impor soal dari Excel. Kesalahan per baris dikirim server dengan kunci
 * "rows.{nomor}" dan ditampilkan berurutan agar mudah diperbaiki di file.
 */
export function QuestionImportDialog({ onClose }: { onClose: () => void }) {
    const form = useForm<{ file: File | null }>({ file: null });

    const rowErrors = Object.entries(form.errors as Record<string, string>)
        .filter(([key]) => key.startsWith('rows.'))
        .sort(([a], [b]) => Number(a.slice(5)) - Number(b.slice(5)))
        .map(([, message]) => message);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(questionImports.store().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('admin.questions.import.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('admin.questions.import.description')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-5">
                    <Button variant="outline" asChild>
                        <a href={questionImports.template().url}>
                            <Download aria-hidden="true" />
                            {t('admin.questions.import.template')}
                        </a>
                    </Button>

                    <FormField
                        id="import-file"
                        label={t('admin.questions.import.file')}
                        description={t('admin.questions.import.fileHelp')}
                        error={form.errors.file}
                    >
                        <Input
                            id="import-file"
                            type="file"
                            required
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            onChange={(event) => {
                                form.clearErrors();
                                form.setData(
                                    'file',
                                    event.target.files?.[0] ?? null,
                                );
                            }}
                        />
                    </FormField>

                    {rowErrors.length > 0 && (
                        <div
                            role="alert"
                            className="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                        >
                            <p className="font-medium">
                                {t('admin.questions.import.rowErrors', {
                                    count: rowErrors.length,
                                })}
                            </p>
                            <ul className="mt-2 max-h-64 list-disc space-y-1 overflow-y-auto pl-5">
                                {rowErrors.map((message) => (
                                    <li key={message}>{message}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                {t('common.cancel')}
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={form.processing || !form.data.file}
                        >
                            {form.processing && <Spinner />}
                            {t('admin.questions.import.submit')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
