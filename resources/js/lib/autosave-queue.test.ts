import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import type { AnswerPayload, SendOutcome } from '@/lib/autosave-queue';
import { AutosaveQueue, readPending, storageKey } from '@/lib/autosave-queue';

function memoryStorage() {
    const data = new Map<string, string>();

    return {
        getItem: (key: string) => data.get(key) ?? null,
        setItem: (key: string, value: string) => void data.set(key, value),
        removeItem: (key: string) => void data.delete(key),
    };
}

const answer = (revision: number, text = 'jawaban'): AnswerPayload => ({
    revision,
    selected: [],
    text,
    flagged: false,
});

describe('AutosaveQueue', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    function makeQueue(
        send: (token: string, payload: AnswerPayload) => Promise<SendOutcome>,
        storage = memoryStorage(),
    ) {
        const statuses: string[] = [];
        const onRejected = vi.fn();
        const queue = new AutosaveQueue({
            attemptId: 'a1',
            send,
            storage,
            onStatus: (status) => statuses.push(status),
            onRejected,
            isOnline: () => true,
        });

        return { queue, statuses, onRejected, storage };
    }

    it('debounces changes and sends only the latest revision', async () => {
        const send = vi.fn(async (): Promise<SendOutcome> => ({
            kind: 'saved',
        }));
        const { queue, statuses } = makeQueue(send);

        queue.queue('q1', answer(1, 'a'));
        queue.queue('q1', answer(2, 'ab'));
        expect(send).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(2000);

        expect(send).toHaveBeenCalledOnce();
        expect(send).toHaveBeenCalledWith('q1', answer(2, 'ab'));
        expect(statuses.at(-1)).toBe('saved');
        expect(queue.pendingCount).toBe(0);
    });

    it('keeps unsent answers in storage and retries after a failure', async () => {
        const send = vi
            .fn<
                (token: string, payload: AnswerPayload) => Promise<SendOutcome>
            >()
            .mockResolvedValueOnce({ kind: 'failed' })
            .mockResolvedValue({ kind: 'saved' });
        const { queue, statuses, storage } = makeQueue(send);

        queue.queue('q1', answer(1));
        await queue.flush();

        expect(statuses.at(-1)).toBe('retrying');
        expect(readPending('a1', storage)).toEqual({ q1: answer(1) });

        await vi.advanceTimersByTimeAsync(5000);

        expect(send).toHaveBeenCalledTimes(2);
        expect(storage.getItem(storageKey('a1'))).toBeNull();
        expect(statuses.at(-1)).toBe('saved');
    });

    it('restores pending answers left by a previous page load', async () => {
        const storage = memoryStorage();
        storage.setItem(storageKey('a1'), JSON.stringify({ q9: answer(4) }));
        const send = vi.fn(async (): Promise<SendOutcome> => ({
            kind: 'saved',
        }));
        const { queue } = makeQueue(send, storage);

        expect(queue.pendingCount).toBe(1);
        await queue.flush();

        expect(send).toHaveBeenCalledWith('q9', answer(4));
    });

    it('stops when the server rejects the attempt', async () => {
        const send = vi.fn(async (): Promise<SendOutcome> => ({
            kind: 'rejected',
            data: { code: 'finished' },
        }));
        const { queue, onRejected } = makeQueue(send);

        queue.queue('q1', answer(1));
        await queue.flush();
        queue.queue('q2', answer(1));
        await vi.advanceTimersByTimeAsync(10_000);

        expect(onRejected).toHaveBeenCalledWith({ code: 'finished' });
        expect(send).toHaveBeenCalledOnce();
    });
});
