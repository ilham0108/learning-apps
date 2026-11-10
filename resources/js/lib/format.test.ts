import { describe, expect, it } from 'vite-plus/test';
import { formatDate, formatDateTime } from '@/lib/format';

const JAKARTA = 'Asia/Jakarta';

describe('formatDate', () => {
    it('does not shift date-only values across time zones', () => {
        expect(formatDate('2026-10-01', JAKARTA)).toBe('1 Okt 2026');
    });

    it('converts UTC timestamps to the display time zone', () => {
        // 17:30 UTC sudah tanggal 29 di Jakarta (UTC+7).
        expect(formatDate('2026-10-28T17:30:00Z', JAKARTA)).toBe('29 Okt 2026');
    });

    it('shows a dash for empty values', () => {
        expect(formatDate(null, JAKARTA)).toBe('—');
    });
});

describe('formatDateTime', () => {
    it('includes the local time and zone name', () => {
        const formatted = formatDateTime('2026-10-28T17:30:00Z', JAKARTA);

        expect(formatted).toContain('29 Okt 2026');
        expect(formatted).toContain('00.30');
        expect(formatted).toContain('WIB');
    });
});
