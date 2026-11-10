import { OptionSelect } from '@/components/app/option-select';
import { SearchInput } from '@/components/app/search-input';
import { t } from '@/lib/i18n';
import type { CategoryOption, Difficulty, QuestionType } from '@/types';

export type QuestionFilterValues = {
    search: string | null;
    category: string | null;
    type: QuestionType | null;
    difficulty: Difficulty | null;
};

export const questionTypes: QuestionType[] = [
    'multiple_choice',
    'multiple_select',
    'true_false',
    'short_answer',
    'essay',
    'case_study',
];

export const difficulties: Difficulty[] = ['easy', 'medium', 'hard'];

/**
 * Filter soal yang dipakai bersama halaman bank soal dan pemilih soal ujian.
 */
export function QuestionFilters({
    values,
    categories,
    onChange,
    idPrefix = 'filter',
}: {
    values: QuestionFilterValues;
    categories: CategoryOption[];
    onChange: (changes: Partial<QuestionFilterValues>) => void;
    idPrefix?: string;
}) {
    return (
        <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap">
            <SearchInput
                value={values.search}
                onSearch={(search) => onChange({ search })}
                placeholder={t('admin.questions.searchPlaceholder')}
                label={t('common.search')}
            />
            <OptionSelect
                id={`${idPrefix}-category`}
                value={values.category}
                onChange={(category) => onChange({ category })}
                options={categories.map((category) => ({
                    value: category.id,
                    label: category.name,
                }))}
                allowAll
                placeholder={t('admin.questions.filters.category')}
                ariaLabel={t('admin.questions.filters.category')}
                className="lg:w-48"
            />
            <OptionSelect
                id={`${idPrefix}-type`}
                value={values.type}
                onChange={(type) =>
                    onChange({ type: type as QuestionType | null })
                }
                options={questionTypes.map((type) => ({
                    value: type,
                    label: t(`status.questionType.${type}`),
                }))}
                allowAll
                ariaLabel={t('admin.questions.filters.type')}
                className="lg:w-52"
            />
            <OptionSelect
                id={`${idPrefix}-difficulty`}
                value={values.difficulty}
                onChange={(difficulty) =>
                    onChange({ difficulty: difficulty as Difficulty | null })
                }
                options={difficulties.map((difficulty) => ({
                    value: difficulty,
                    label: t(`status.difficulty.${difficulty}`),
                }))}
                allowAll
                ariaLabel={t('admin.questions.filters.difficulty')}
                className="lg:w-40"
            />
        </div>
    );
}
