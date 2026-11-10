import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import type { PaginationMeta } from '@/types';

export function Pagination({
    meta,
    onPageChange,
}: {
    meta: PaginationMeta;
    onPageChange: (page: number) => void;
}) {
    if (meta.total === 0) {
        return null;
    }

    return (
        <nav
            className="flex flex-col items-center justify-between gap-3 text-sm sm:flex-row"
            aria-label={t('common.pagination.label')}
        >
            <p className="text-muted-foreground">
                {t('common.pagination.summary', {
                    from: meta.from ?? 0,
                    to: meta.to ?? 0,
                    total: meta.total,
                })}
            </p>
            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={meta.current_page <= 1}
                    onClick={() => onPageChange(meta.current_page - 1)}
                >
                    <ChevronLeft aria-hidden="true" />
                    {t('common.pagination.previous')}
                </Button>
                <span
                    className="px-2 whitespace-nowrap text-muted-foreground"
                    aria-current="page"
                >
                    {t('common.pagination.page', {
                        page: meta.current_page,
                        last: meta.last_page,
                    })}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => onPageChange(meta.current_page + 1)}
                >
                    {t('common.pagination.next')}
                    <ChevronRight aria-hidden="true" />
                </Button>
            </div>
        </nav>
    );
}
