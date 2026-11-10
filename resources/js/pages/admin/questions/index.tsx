import { Head, Link } from '@inertiajs/react';
import { Plus, Tags } from 'lucide-react';
import type { Column } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { QuestionStatusBadge } from '@/components/app/exam-badges';
import { OptionSelect } from '@/components/app/option-select';
import { PageHeader } from '@/components/app/page-header';
import { Pagination } from '@/components/app/pagination';
import { QuestionFilters } from '@/components/questions/question-filters';
import type { QuestionFilterValues } from '@/components/questions/question-filters';
import { Button } from '@/components/ui/button';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { t } from '@/lib/i18n';
import questionCategories from '@/routes/admin/question-categories';
import questions from '@/routes/admin/questions';
import type {
    CategoryOption,
    Paginated,
    QuestionListItem,
    QuestionStatus,
} from '@/types';

type Filters = QuestionFilterValues & { status: QuestionStatus | null };

type Props = {
    questions: Paginated<QuestionListItem>;
    filters: Filters;
    categories: CategoryOption[];
};

export default function QuestionsIndex({
    questions: page,
    filters,
    categories,
}: Props) {
    const apply = useQueryFilters(questions.index().url, filters);
    const isFiltered = Object.values(filters).some((value) => value !== null);

    const columns: Column<QuestionListItem>[] = [
        {
            key: 'question',
            header: t('admin.questions.columns.question'),
            cell: (question) => (
                <div className="min-w-64">
                    <Link
                        href={questions.edit(question.id)}
                        className="font-medium text-primary hover:underline"
                    >
                        {question.excerpt}
                    </Link>
                    <div className="text-xs text-muted-foreground">
                        {[
                            question.topic,
                            question.version > 1
                                ? t('admin.questions.version', {
                                      version: question.version,
                                  })
                                : null,
                            question.type === 'case_study'
                                ? t('admin.questions.subQuestions', {
                                      count: question.children_count ?? 0,
                                  })
                                : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </div>
                </div>
            ),
        },
        {
            key: 'type',
            header: t('admin.questions.columns.type'),
            className: 'whitespace-nowrap',
            cell: (question) => t(`status.questionType.${question.type}`),
        },
        {
            key: 'category',
            header: t('admin.questions.columns.category'),
            cell: (question) => question.category,
        },
        {
            key: 'difficulty',
            header: t('admin.questions.columns.difficulty'),
            cell: (question) => t(`status.difficulty.${question.difficulty}`),
        },
        {
            key: 'usage',
            header: t('admin.questions.columns.usage'),
            className: 'whitespace-nowrap',
            cell: (question) =>
                t('admin.questions.usedIn', {
                    count: question.usage_count ?? 0,
                }),
        },
        {
            key: 'status',
            header: t('admin.questions.columns.status'),
            cell: (question) => (
                <QuestionStatusBadge status={question.status} />
            ),
        },
    ];

    return (
        <>
            <Head title={t('admin.questions.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.questions.title')}
                    description={t('admin.questions.description')}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={questionCategories.index()}>
                                    <Tags aria-hidden="true" />
                                    {t('admin.questions.manageCategories')}
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={questions.create()}>
                                    <Plus aria-hidden="true" />
                                    {t('admin.questions.create')}
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap">
                    <QuestionFilters
                        values={filters}
                        categories={categories}
                        onChange={apply}
                    />
                    <OptionSelect
                        id="filter-status"
                        value={filters.status}
                        onChange={(status) =>
                            apply({ status: status as QuestionStatus | null })
                        }
                        options={[
                            {
                                value: 'active',
                                label: t('status.question.active'),
                            },
                            {
                                value: 'archived',
                                label: t('status.question.archived'),
                            },
                        ]}
                        allowAll
                        ariaLabel={t('admin.questions.filters.status')}
                        className="lg:w-40"
                    />
                </div>

                <DataTable
                    columns={columns}
                    rows={page.data}
                    caption={t('admin.questions.title')}
                    empty={
                        <EmptyState
                            title={
                                isFiltered
                                    ? t('common.noResults')
                                    : t('admin.questions.empty')
                            }
                            description={
                                isFiltered
                                    ? t('common.noResultsDescription')
                                    : undefined
                            }
                        />
                    }
                />

                <Pagination
                    meta={page.meta}
                    onPageChange={(pageNumber) => apply({ page: pageNumber })}
                />
            </div>
        </>
    );
}

QuestionsIndex.layout = {
    breadcrumbs: [
        { title: t('admin.questions.title'), href: questions.index() },
    ],
};
