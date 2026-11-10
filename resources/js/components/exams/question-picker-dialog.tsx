import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { EmptyState, ErrorState } from '@/components/app/empty-state';
import { Pagination } from '@/components/app/pagination';
import { QuestionFilters } from '@/components/questions/question-filters';
import type { QuestionFilterValues } from '@/components/questions/question-filters';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import exams from '@/routes/admin/exams';
import type { CategoryOption, Paginated, QuestionListItem } from '@/types';

type Status = 'loading' | 'ready' | 'error';

/**
 * Memilih soal dari bank soal. Data diambil dari endpoint JSON tanpa kunci jawaban.
 */
export function QuestionPickerDialog({
    examId,
    categories,
    onClose,
}: {
    examId: string;
    categories: CategoryOption[];
    onClose: () => void;
}) {
    const [filters, setFilters] = useState<QuestionFilterValues>({
        search: null,
        category: null,
        type: null,
        difficulty: null,
    });
    const [page, setPage] = useState(1);
    const [result, setResult] = useState<Paginated<QuestionListItem> | null>(
        null,
    );
    const [status, setStatus] = useState<Status>('loading');
    const [selected, setSelected] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        const query = Object.fromEntries(
            Object.entries({ ...filters, page }).filter(
                ([, value]) => value !== null,
            ),
        ) as Record<string, string | number>;

        setStatus('loading');
        fetch(exams.questionPicker(examId, { query }).url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                return response.json() as Promise<Paginated<QuestionListItem>>;
            })
            .then((data) => {
                setResult(data);
                setStatus('ready');
            })
            .catch((error: unknown) => {
                if (
                    !(
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    )
                ) {
                    setStatus('error');
                }
            });

        return () => controller.abort();
    }, [examId, filters, page]);

    const toggle = (id: string, checked: boolean) =>
        setSelected((current) =>
            checked
                ? [...current, id]
                : current.filter((value) => value !== id),
        );

    const submit = () =>
        router.visit(exams.questions.store(examId), {
            data: { questions: selected },
            preserveScroll: true,
            onStart: () => setSubmitting(true),
            onFinish: () => setSubmitting(false),
            onSuccess: onClose,
        });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('admin.exams.questions.pickerTitle')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('admin.exams.questions.pickerDescription')}
                    </DialogDescription>
                </DialogHeader>

                <QuestionFilters
                    values={filters}
                    categories={categories}
                    idPrefix="picker"
                    onChange={(changes) => {
                        setFilters((current) => ({ ...current, ...changes }));
                        setPage(1);
                    }}
                />

                <div aria-live="polite" aria-busy={status === 'loading'}>
                    {status === 'loading' && (
                        <div className="grid gap-2">
                            {[0, 1, 2, 3].map((index) => (
                                <Skeleton key={index} className="h-14 w-full" />
                            ))}
                        </div>
                    )}
                    {status === 'error' && (
                        <ErrorState
                            title={t('errors.500.title')}
                            description={t('errors.500.description')}
                        />
                    )}
                    {status === 'ready' &&
                        result &&
                        result.data.length === 0 && (
                            <EmptyState
                                title={t('admin.exams.questions.pickerEmpty')}
                            />
                        )}
                    {status === 'ready' && result && result.data.length > 0 && (
                        <ul className="divide-y rounded-lg border">
                            {result.data.map((question) => (
                                <li
                                    key={question.id}
                                    className="flex items-start gap-3 p-3"
                                >
                                    <Checkbox
                                        id={`pick-${question.id}`}
                                        checked={selected.includes(question.id)}
                                        onCheckedChange={(checked) =>
                                            toggle(
                                                question.id,
                                                checked === true,
                                            )
                                        }
                                        aria-label={t(
                                            'admin.exams.questions.select',
                                            { text: question.excerpt },
                                        )}
                                    />
                                    <label
                                        htmlFor={`pick-${question.id}`}
                                        className="grid gap-0.5 text-sm"
                                    >
                                        <span>{question.excerpt}</span>
                                        <span className="text-xs text-muted-foreground">
                                            {[
                                                t(
                                                    `status.questionType.${question.type}`,
                                                ),
                                                question.category,
                                                t(
                                                    `status.difficulty.${question.difficulty}`,
                                                ),
                                            ].join(' · ')}
                                        </span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {result && (
                    <Pagination meta={result.meta} onPageChange={setPage} />
                )}

                <DialogFooter className="items-center gap-2">
                    <span className="mr-auto text-sm text-muted-foreground">
                        {t('admin.exams.questions.selected', {
                            count: selected.length,
                        })}
                    </span>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('common.cancel')}
                    </Button>
                    <Button
                        type="button"
                        onClick={submit}
                        disabled={selected.length === 0 || submitting}
                    >
                        {submitting && <Spinner />}
                        {t('admin.exams.questions.addSelected')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
