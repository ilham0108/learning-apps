/**
 * Antrean autosave jawaban ujian (risiko R14).
 *
 * - Perubahan per soal ditunda 2 detik (debounce); `flush()` mengirim segera,
 *   misalnya saat pindah soal.
 * - Jawaban yang belum terkirim disimpan di localStorage per attempt, sehingga
 *   tetap ada setelah refresh atau koneksi putus, lalu dikirim ulang.
 * - Setiap jawaban membawa `revision` yang naik terus; server menolak revisi
 *   lama, jadi kiriman ulang tidak menimpa jawaban yang lebih baru (aturan B13).
 */
export type AnswerPayload = {
    revision: number;
    selected: string[];
    text: string | null;
    flagged: boolean;
};

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'retrying' | 'offline';

/** Hasil satu kiriman: tersimpan, ditolak permanen, ditolak aturan ujian, atau gagal sementara. */
export type SendOutcome =
    | { kind: 'saved' }
    | { kind: 'invalid' }
    | { kind: 'rejected'; data: unknown }
    | { kind: 'failed' };

type Options = {
    attemptId: string;
    send: (token: string, payload: AnswerPayload) => Promise<SendOutcome>;
    onStatus: (status: SaveStatus, pendingCount: number) => void;
    onRejected: (data: unknown) => void;
    onInvalid?: (token: string) => void;
    debounceMs?: number;
    retryMs?: number;
    storage?: Pick<Storage, 'getItem' | 'setItem' | 'removeItem'> | null;
    isOnline?: () => boolean;
};

export const storageKey = (attemptId: string) =>
    `exam-attempt:${attemptId}:pending`;

function defaultStorage() {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

/** Membaca jawaban yang belum terkirim dari sesi sebelumnya. */
export function readPending(
    attemptId: string,
    storage: Pick<Storage, 'getItem'> | null | undefined = defaultStorage(),
): Record<string, AnswerPayload> {
    try {
        const raw = storage?.getItem(storageKey(attemptId));

        return raw ? (JSON.parse(raw) as Record<string, AnswerPayload>) : {};
    } catch {
        return {};
    }
}

export class AutosaveQueue {
    private pending: Record<string, AnswerPayload>;
    private timer: ReturnType<typeof setTimeout> | null = null;
    private sending = false;
    private stopped = false;
    private readonly options: Required<Omit<Options, 'storage' | 'onInvalid'>> &
        Pick<Options, 'storage' | 'onInvalid'>;

    constructor(options: Options) {
        this.options = {
            debounceMs: 2000,
            retryMs: 5000,
            isOnline: () =>
                typeof navigator === 'undefined' || navigator.onLine,
            ...options,
            storage:
                options.storage === undefined
                    ? defaultStorage()
                    : options.storage,
        };
        this.pending = readPending(options.attemptId, this.options.storage);
    }

    get pendingCount(): number {
        return Object.keys(this.pending).length;
    }

    /** Menjadwalkan penyimpanan satu jawaban. */
    queue(token: string, payload: AnswerPayload): void {
        if (this.stopped) {
            return;
        }

        this.pending[token] = payload;
        this.persist();
        this.options.onStatus(
            this.options.isOnline() ? 'saving' : 'offline',
            this.pendingCount,
        );
        this.schedule(this.options.debounceMs);
    }

    /** Mengirim semua jawaban tertunda sekarang. */
    async flush(): Promise<void> {
        this.clearTimer();

        if (this.sending || this.stopped || this.pendingCount === 0) {
            return;
        }

        this.sending = true;
        this.options.onStatus('saving', this.pendingCount);
        let failed = false;

        for (const [token, payload] of Object.entries(this.pending)) {
            let outcome: SendOutcome;

            try {
                outcome = await this.options.send(token, payload);
            } catch {
                outcome = { kind: 'failed' };
            }

            if (outcome.kind === 'rejected') {
                this.sending = false;
                this.stop();
                this.options.onRejected(outcome.data);

                return;
            }

            if (outcome.kind === 'failed') {
                failed = true;
                break;
            }

            if (outcome.kind === 'invalid') {
                this.options.onInvalid?.(token);
            }

            // Hapus hanya jika tidak ada perubahan baru selama pengiriman.
            if (this.pending[token]?.revision === payload.revision) {
                delete this.pending[token];
                this.persist();
            }
        }

        this.sending = false;

        if (failed) {
            this.options.onStatus(
                this.options.isOnline() ? 'retrying' : 'offline',
                this.pendingCount,
            );
            this.schedule(this.options.retryMs);

            return;
        }

        if (this.pendingCount > 0) {
            // Ada perubahan baru selama pengiriman.
            this.schedule(this.options.debounceMs);
            this.options.onStatus('saving', this.pendingCount);

            return;
        }

        this.options.onStatus('saved', 0);
    }

    /** Menghentikan antrean (attempt selesai atau sesi diganti). */
    stop(): void {
        this.stopped = true;
        this.clearTimer();
    }

    /** Menghapus salinan lokal setelah attempt selesai. */
    clear(): void {
        this.pending = {};

        try {
            this.options.storage?.removeItem(
                storageKey(this.options.attemptId),
            );
        } catch {
            // Penyimpanan lokal tidak tersedia; tidak ada yang perlu dihapus.
        }
    }

    private schedule(ms: number): void {
        this.clearTimer();
        this.timer = setTimeout(() => void this.flush(), ms);
    }

    private clearTimer(): void {
        if (this.timer !== null) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    }

    private persist(): void {
        try {
            if (this.pendingCount === 0) {
                this.options.storage?.removeItem(
                    storageKey(this.options.attemptId),
                );
            } else {
                this.options.storage?.setItem(
                    storageKey(this.options.attemptId),
                    JSON.stringify(this.pending),
                );
            }
        } catch {
            // Mode privat / penyimpanan penuh: antrean tetap berjalan di memori.
        }
    }
}
