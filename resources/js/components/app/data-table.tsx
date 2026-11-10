import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type SortDirection = 'asc' | 'desc';

export type Column<Row> = {
    key: string;
    header: string;
    cell: (row: Row) => ReactNode;
    /** Kunci urutan yang dikirim ke server; kolom tanpa nilai ini tidak bisa diurutkan. */
    sortKey?: string;
    className?: string;
};

type Props<Row extends { id: string }> = {
    columns: Column<Row>[];
    rows: Row[];
    sort?: string;
    direction?: SortDirection;
    onSort?: (sortKey: string, direction: SortDirection) => void;
    empty: ReactNode;
    caption?: string;
};

/**
 * Tabel dengan pengurutan di server. Pada layar kecil tabel dapat digeser
 * horizontal di dalam wadahnya tanpa membuat halaman ikut bergeser.
 */
export function DataTable<Row extends { id: string }>({
    columns,
    rows,
    sort,
    direction = 'asc',
    onSort,
    empty,
    caption,
}: Props<Row>) {
    if (rows.length === 0) {
        return <>{empty}</>;
    }

    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                {caption && <caption className="sr-only">{caption}</caption>}
                <thead className="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        {columns.map((column) => {
                            const isSorted =
                                column.sortKey !== undefined &&
                                column.sortKey === sort;
                            const SortIcon = isSorted
                                ? direction === 'asc'
                                    ? ArrowUp
                                    : ArrowDown
                                : ArrowUpDown;

                            return (
                                <th
                                    key={column.key}
                                    scope="col"
                                    className={cn(
                                        'px-4 py-3 font-medium whitespace-nowrap',
                                        column.className,
                                    )}
                                    aria-sort={
                                        isSorted
                                            ? direction === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : undefined
                                    }
                                >
                                    {column.sortKey && onSort ? (
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-1 rounded hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            onClick={() =>
                                                onSort(
                                                    column.sortKey!,
                                                    isSorted &&
                                                        direction === 'asc'
                                                        ? 'desc'
                                                        : 'asc',
                                                )
                                            }
                                            aria-label={t('common.sortBy', {
                                                column: column.header,
                                            })}
                                        >
                                            {column.header}
                                            <SortIcon
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    ) : (
                                        column.header
                                    )}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={row.id}
                            className="border-t align-top hover:bg-muted/30"
                        >
                            {columns.map((column) => (
                                <td
                                    key={column.key}
                                    className={cn(
                                        'px-4 py-3',
                                        column.className,
                                    )}
                                >
                                    {column.cell(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
