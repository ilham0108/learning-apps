/**
 * Permintaan JSON ke endpoint web Laravel (sesi + CSRF). Dipakai runner ujian,
 * yang tidak memakai kunjungan Inertia agar halaman tidak dimuat ulang.
 */
export type JsonResult<T> = {
    ok: boolean;
    status: number;
    data: T;
};

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Melempar error hanya jika jaringan gagal; status HTTP apa pun dikembalikan
 * agar pemanggil dapat membedakan 409 (ditolak aturan ujian) dari 5xx.
 */
export async function jsonRequest<T = unknown>(
    method: 'GET' | 'POST' | 'PUT',
    url: string,
    body?: unknown,
    headers: Record<string, string> = {},
): Promise<JsonResult<T>> {
    const token = xsrfToken();
    const init: RequestInit = {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(body === undefined
                ? {}
                : { 'Content-Type': 'application/json' }),
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            ...headers,
        },
    };

    if (body !== undefined && method !== 'GET') {
        init.body = JSON.stringify(body);
    }

    const response = await fetch(url, init);

    let data: unknown = null;

    try {
        data = await response.json();
    } catch {
        data = null;
    }

    return { ok: response.ok, status: response.status, data: data as T };
}
