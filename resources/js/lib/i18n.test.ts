import { describe, expect, it } from 'vite-plus/test';
import { t, tList } from '@/lib/i18n';
import type { TranslationKey } from '@/lib/i18n';

describe('t', () => {
    it('returns the Indonesian text for a key', () => {
        expect(t('auth.login.submit')).toBe('Masuk');
    });

    it('replaces every placeholder occurrence', () => {
        expect(t('admin.dashboard.greeting', { name: 'Rina' })).toBe(
            'Selamat datang, Rina',
        );
    });

    it('does not replace placeholders that share a prefix', () => {
        expect(
            t('common.pagination.summary', { from: 1, to: 15, total: 40 }),
        ).toBe('Menampilkan 1–15 dari 40');
    });

    it('falls back to the key when the text does not exist', () => {
        expect(t('does.not.exist' as TranslationKey)).toBe('does.not.exist');
    });

    it('returns list entries through tList', () => {
        expect(tList('auth.consent.points').length).toBeGreaterThan(0);
        expect(tList('auth.login.submit')).toEqual([]);
    });
});
