/**
 * Waktu dari server dikirim dalam UTC (ISO 8601) dan ditampilkan di zona
 * Asia/Jakarta (prop bersama `displayTimezone`).
 */
const LOCALE = 'id-ID';

export function formatDate(value: string | null, timeZone: string): string {
    if (value === null) {
        return '—';
    }

    // Tanggal tanpa jam (YYYY-MM-DD) tidak dikonversi zona waktunya.
    const isDateOnly = /^\d{4}-\d{2}-\d{2}$/.test(value);

    return new Intl.DateTimeFormat(LOCALE, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: isDateOnly ? 'UTC' : timeZone,
    }).format(new Date(isDateOnly ? `${value}T00:00:00Z` : value));
}

export function formatDateTime(value: string | null, timeZone: string): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat(LOCALE, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone,
        timeZoneName: 'short',
    }).format(new Date(value));
}
