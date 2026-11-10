import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { FormField } from '@/components/app/form-field';
import { PageHeader } from '@/components/app/page-header';
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
import questionCategories from '@/routes/admin/question-categories';
import questions from '@/routes/admin/questions';

type Category = {
    id: string;
    name: string;
    description: string | null;
    questions_count: number;
};

export default function QuestionCategoriesIndex({
    categories,
}: {
    categories: Category[];
}) {
    const [editing, setEditing] = useState<Category | 'new' | null>(null);

    return (
        <>
            <Head title={t('admin.questionCategories.title')} />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.questionCategories.title')}
                    description={t('admin.questionCategories.description')}
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden="true" />
                            {t('admin.questionCategories.add')}
                        </Button>
                    }
                />

                {categories.length === 0 ? (
                    <EmptyState title={t('admin.questionCategories.empty')} />
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {categories.map((category) => (
                            <li
                                key={category.id}
                                className="flex items-start justify-between gap-3 p-4"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium">
                                        {category.name}
                                    </p>
                                    {category.description && (
                                        <p className="text-sm text-muted-foreground">
                                            {category.description}
                                        </p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'admin.questionCategories.questionsCount',
                                            { count: category.questions_count },
                                        )}
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t(
                                        'admin.questionCategories.edit',
                                    )}
                                    title={t('admin.questionCategories.edit')}
                                    onClick={() => setEditing(category)}
                                >
                                    <Pencil aria-hidden="true" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {editing !== null && (
                <CategoryDialog
                    category={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function CategoryDialog({
    category,
    onClose,
}: {
    category: Category | null;
    onClose: () => void;
}) {
    const form = useForm({
        name: category?.name ?? '',
        description: category?.description ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(
            category
                ? questionCategories.update(category.id)
                : questionCategories.store(),
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
                            {category
                                ? t('admin.questionCategories.edit')
                                : t('admin.questionCategories.add')}
                        </DialogTitle>
                    </DialogHeader>
                    <FormField
                        id="category-name"
                        label={t('admin.questionCategories.name')}
                        error={form.errors.name}
                    >
                        <Input
                            id="category-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                            autoFocus
                        />
                    </FormField>
                    <FormField
                        id="category-description"
                        label={t('admin.questionCategories.descriptionLabel')}
                        error={form.errors.description}
                        optional
                    >
                        <Textarea
                            id="category-description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            maxLength={1000}
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

QuestionCategoriesIndex.layout = {
    breadcrumbs: [
        { title: t('admin.questions.title'), href: questions.index() },
        {
            title: t('admin.questionCategories.title'),
            href: questionCategories.index(),
        },
    ],
};
