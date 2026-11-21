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

export type RowSelection<Row> = {
    selected: string[];
    onChange: (ids: string[]) => void;
    /** Label aksesibel kotak centang per baris. */
    label: (row: Row) => string;
};

type Props<Row extends { id: string }> = {
    columns: Column<Row>[];
    rows: Row[];
    sort?: string;
    direction?: SortDirection;
    onSort?: (sortKey: string, direction: SortDirection) => void;
    empty: ReactNode;
    caption?: string;
    /** Aktifkan kolom kotak centang untuk aksi massal. */
    selection?: RowSelection<Row>;
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
    selection,
}: Props<Row>) {
    if (rows.length === 0) {
        return <>{empty}</>;
    }

    const selectedOnPage = selection
        ? rows.filter((row) => selection.selected.includes(row.id))
        : [];
    const allSelected = selectedOnPage.length === rows.length;
    const toggleAll = () =>
        selection?.onChange(allSelected ? [] : rows.map((row) => row.id));
    const toggleRow = (id: string, checked: boolean) =>
        selection?.onChange(
            checked
                ? [...selection.selected, id]
                : selection.selected.filter((item) => item !== id),
        );

    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                {caption && <caption className="sr-only">{caption}</caption>}
                <thead className="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        {selection && (
                            <th scope="col" className="w-10 px-4 py-3">
                                <input
                                    type="checkbox"
                                    checked={allSelected}
                                    ref={(input) => {
                                        if (input) {
                                            input.indeterminate =
                                                selectedOnPage.length > 0 &&
                                                !allSelected;
                                        }
                                    }}
                                    onChange={toggleAll}
                                    aria-label={t('common.selectAll')}
                                    className="size-4 accent-primary"
                                />
                            </th>
                        )}
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
                            className={cn(
                                'border-t align-top hover:bg-muted/30',
                                selection?.selected.includes(row.id) &&
                                    'bg-muted/40',
                            )}
                        >
                            {selection && (
                                <td className="w-10 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        checked={selection.selected.includes(
                                            row.id,
                                        )}
                                        onChange={(event) =>
                                            toggleRow(
                                                row.id,
                                                event.target.checked,
                                            )
                                        }
                                        aria-label={selection.label(row)}
                                        className="mt-0.5 size-4 accent-primary"
                                    />
                                </td>
                            )}
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
