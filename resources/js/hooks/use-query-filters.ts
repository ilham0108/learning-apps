import { router } from '@inertiajs/react';
import { useCallback } from 'react';

export type QueryValue = string | number | null | undefined;

/**
 * Menyimpan filter, urutan, dan halaman tabel di query string agar bisa
 * dibagikan dan dimuat ulang. Setiap perubahan filter kembali ke halaman 1.
 */
export function useQueryFilters<T extends Record<string, QueryValue>>(
    url: string,
    current: T,
) {
    return useCallback(
        (changes: Partial<T> & { page?: number }) => {
            const next: Record<string, QueryValue> = { ...current, ...changes };

            if (!('page' in changes)) {
                delete next.page;
            }

            const query = Object.fromEntries(
                Object.entries(next).filter(
                    ([, value]) =>
                        value !== null && value !== undefined && value !== '',
                ),
            );

            router.get(url, query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [url, current],
    );
}
