import { id } from '@/i18n/id';
import type { Dictionary } from '@/i18n/id';

type Leaves<T, Prefix extends string = ''> = {
    [K in keyof T & (string | number)]: T[K] extends string
        ? `${Prefix}${K}`
        : T[K] extends readonly string[]
          ? `${Prefix}${K}`
          : Leaves<T[K], `${Prefix}${K}.`>;
}[keyof T & (string | number)];

/** Semua kunci teks yang valid, misalnya `'auth.login.submit'`. */
export type TranslationKey = Leaves<Dictionary>;

type Replacements = Record<string, string | number>;

function lookup(key: string): unknown {
    return key
        .split('.')
        .reduce<unknown>(
            (node, part) =>
                node !== null && typeof node === 'object'
                    ? (node as Record<string, unknown>)[part]
                    : undefined,
            id,
        );
}

/**
 * Mengambil teks dari kamus dan mengganti placeholder `:nama`.
 * Kunci yang tidak ada mengembalikan kunci itu sendiri agar mudah ditemukan.
 */
export function t(
    key: TranslationKey,
    replacements: Replacements = {},
): string {
    const value = lookup(key);

    if (typeof value !== 'string') {
        return key;
    }

    // Ganti per nama utuh agar `:to` tidak ikut mengganti bagian dari `:total`.
    return value.replace(/:([A-Za-z_]+)/g, (placeholder, name: string) =>
        name in replacements ? String(replacements[name]) : placeholder,
    );
}

/** Mengambil daftar teks, misalnya poin-poin kebijakan. */
export function tList(key: TranslationKey): readonly string[] {
    const value = lookup(key);

    return Array.isArray(value) ? (value as string[]) : [];
}
