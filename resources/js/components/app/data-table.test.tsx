import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import type { Column } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { Pagination } from '@/components/app/pagination';

type Row = { id: string; name: string };

const columns: Column<Row>[] = [
    { key: 'name', header: 'Nama', sortKey: 'name', cell: (row) => row.name },
    { key: 'note', header: 'Catatan', cell: () => '-' },
];

describe('DataTable', () => {
    it('renders the empty state when there are no rows', () => {
        render(<DataTable columns={columns} rows={[]} empty={<p>Kosong</p>} />);

        expect(screen.getByText('Kosong')).toBeInTheDocument();
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    it('marks the sorted column and toggles the direction', async () => {
        const onSort = vi.fn();

        render(
            <DataTable
                columns={columns}
                rows={[{ id: 'a', name: 'Budi' }]}
                sort="name"
                direction="asc"
                onSort={onSort}
                empty={null}
            />,
        );

        expect(
            screen.getByRole('columnheader', { name: /Nama/ }),
        ).toHaveAttribute('aria-sort', 'ascending');

        await userEvent.click(
            screen.getByRole('button', { name: 'Urutkan berdasarkan Nama' }),
        );
        expect(onSort).toHaveBeenCalledWith('name', 'desc');
    });

    it('does not offer sorting on columns without a sort key', () => {
        render(
            <DataTable
                columns={columns}
                rows={[{ id: 'a', name: 'Budi' }]}
                onSort={vi.fn()}
                empty={null}
            />,
        );

        expect(
            screen.queryByRole('button', { name: /Catatan/ }),
        ).not.toBeInTheDocument();
    });
});

describe('Pagination', () => {
    const meta = {
        current_page: 1,
        last_page: 3,
        per_page: 15,
        total: 40,
        from: 1,
        to: 15,
    };

    it('shows the range and disables the previous button on the first page', async () => {
        const onPageChange = vi.fn();
        render(<Pagination meta={meta} onPageChange={onPageChange} />);

        expect(
            screen.getByText('Menampilkan 1–15 dari 40'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Sebelumnya/ }),
        ).toBeDisabled();

        await userEvent.click(
            screen.getByRole('button', { name: /Berikutnya/ }),
        );
        expect(onPageChange).toHaveBeenCalledWith(2);
    });

    it('renders nothing when there is no data', () => {
        const { container } = render(
            <Pagination
                meta={{ ...meta, total: 0, from: null, to: null }}
                onPageChange={vi.fn()}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });
});
