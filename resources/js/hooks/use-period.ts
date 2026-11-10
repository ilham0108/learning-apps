import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import { formatDate } from '@/lib/format';

/** Memformat rentang tanggal "1 Okt 2026 – 30 Nov 2026" di zona waktu tampilan. */
export function usePeriod() {
    const { displayTimezone } = usePage().props;

    return useCallback(
        (start: string, end: string | null) =>
            `${formatDate(start, displayTimezone)} – ${formatDate(end, displayTimezone)}`,
        [displayTimezone],
    );
}
