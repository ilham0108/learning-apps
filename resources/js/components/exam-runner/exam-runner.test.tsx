import { act, render, renderHook, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    CountdownTimer,
    formatRemaining,
} from '@/components/exam-runner/countdown-timer';
import { QuestionNavigator } from '@/components/exam-runner/question-navigator';
import { SaveIndicator } from '@/components/exam-runner/save-indicator';
import { SubmitDialog } from '@/components/exam-runner/submit-dialog';
import { useCountdown } from '@/hooks/use-countdown';

afterEach(() => {
    vi.useRealTimers();
});

describe('timer', () => {
    it('formats the remaining time', () => {
        expect(formatRemaining(125_000)).toBe('02:05');
        expect(formatRemaining(3_725_000)).toBe('1:02:05');
        expect(formatRemaining(-5)).toBe('00:00');
        // Sisa 0,4 detik masih ditampilkan sebagai 1 detik, bukan 0.
        expect(formatRemaining(400)).toBe('00:01');
    });

    it('announces a warning only below five minutes', () => {
        const { rerender } = render(<CountdownTimer remainingMs={600_000} />);

        expect(screen.getByRole('timer')).toHaveTextContent('10:00');
        expect(
            screen.queryByText('Sisa waktu kurang dari 5 menit.'),
        ).not.toBeInTheDocument();

        rerender(<CountdownTimer remainingMs={299_000} />);

        expect(
            screen.getByText('Sisa waktu kurang dari 5 menit.'),
        ).toBeInTheDocument();
    });

    it('counts down from server time, ignoring the device clock', () => {
        vi.useFakeTimers();
        // Jam perangkat 1 jam lebih lambat dari server.
        vi.setSystemTime(new Date('2026-11-10T02:00:00Z'));
        const onExpire = vi.fn();

        const { result } = renderHook(() =>
            useCountdown(
                '2026-11-10T03:00:10Z',
                '2026-11-10T03:00:00Z',
                onExpire,
            ),
        );

        expect(result.current).toBe(10_000);

        act(() => {
            vi.advanceTimersByTime(4_000);
        });
        expect(result.current).toBe(6_000);
        expect(onExpire).not.toHaveBeenCalled();

        act(() => {
            vi.advanceTimersByTime(8_000);
        });
        expect(result.current).toBe(0);
        expect(onExpire).toHaveBeenCalledOnce();
    });
});

describe('QuestionNavigator', () => {
    const items = [
        { position: 1, answered: true, flagged: false },
        { position: 2, answered: false, flagged: true },
        { position: 3, answered: false, flagged: false },
    ];

    it('labels each question with its state in text', () => {
        render(
            <QuestionNavigator
                items={items}
                current={2}
                forwardOnly={false}
                onSelect={() => undefined}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Soal 1, Dijawab' }),
        ).toBeInTheDocument();
        const current = screen.getByRole('button', {
            name: 'Soal 2, Belum dijawab, Ragu',
        });
        expect(current).toHaveAttribute('aria-current', 'step');
    });

    it('moves to the selected question', async () => {
        const onSelect = vi.fn();
        render(
            <QuestionNavigator
                items={items}
                current={1}
                forwardOnly={false}
                onSelect={onSelect}
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: /^Soal 3/ }));

        expect(onSelect).toHaveBeenCalledWith(3);
    });

    it('locks other questions in forward-only mode', async () => {
        const onSelect = vi.fn();
        render(
            <QuestionNavigator
                items={items}
                current={2}
                forwardOnly
                onSelect={onSelect}
            />,
        );

        const first = screen.getByRole('button', { name: /^Soal 1/ });
        expect(first).toBeDisabled();
        expect(first).toHaveAccessibleName('Soal 1, Dijawab, Terkunci');

        await userEvent.click(first);
        expect(onSelect).not.toHaveBeenCalled();
    });
});

describe('SaveIndicator', () => {
    it.each([
        ['saving', 'Menyimpan…'],
        ['saved', 'Tersimpan'],
        ['retrying', 'Gagal tersimpan, mencoba lagi'],
        ['offline', 'Offline, jawaban disimpan di perangkat ini'],
    ] as const)('shows %s as text in a live region', (status, label) => {
        render(<SaveIndicator status={status} />);

        expect(screen.getByRole('status')).toHaveTextContent(label);
    });
});

describe('SubmitDialog', () => {
    function renderDialog(
        props: Partial<Parameters<typeof SubmitDialog>[0]> = {},
    ) {
        const onConfirm = vi.fn();
        render(
            <SubmitDialog
                open
                onOpenChange={() => undefined}
                unanswered={2}
                flagged={1}
                pendingSaves={0}
                processing={false}
                onConfirm={onConfirm}
                {...props}
            />,
        );

        return onConfirm;
    }

    it('summarises unanswered and flagged questions', () => {
        renderDialog();

        expect(
            screen.getByRole('dialog', { name: 'Kumpulkan ujian?' }),
        ).toBeInTheDocument();
        expect(screen.getByText('2 soal belum dijawab')).toBeInTheDocument();
        expect(screen.getByText('1 soal ditandai ragu')).toBeInTheDocument();
    });

    it('confirms the submission', async () => {
        const onConfirm = renderDialog({ unanswered: 0, flagged: 0 });

        expect(
            screen.getByText('Semua soal sudah dijawab.'),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Kumpulkan sekarang' }),
        );

        expect(onConfirm).toHaveBeenCalledOnce();
    });

    it('blocks submission while answers are still unsent', () => {
        renderDialog({ pendingSaves: 3 });

        expect(
            screen.getByText(/3 jawaban belum terkirim/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Kumpulkan sekarang' }),
        ).toBeDisabled();
    });
});
