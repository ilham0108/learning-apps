import { t } from '@/lib/i18n';
import { TimerIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';

const WARNING_MS = 5 * 60 * 1000;

/** 3725000 ms → "1:02:05"; 125000 ms → "02:05". */
export function formatRemaining(ms: number): string {
    const totalSeconds = Math.max(0, Math.ceil(ms / 1000));
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    const pad = (value: number) => String(value).padStart(2, '0');

    return hours > 0
        ? `${hours}:${pad(minutes)}:${pad(seconds)}`
        : `${pad(minutes)}:${pad(seconds)}`;
}

/**
 * Tampilan sisa waktu. Di bawah 5 menit berubah warna dan mengumumkan
 * peringatan satu kali ke pembaca layar (bukan setiap detik).
 */
export function CountdownTimer({ remainingMs }: { remainingMs: number }) {
    const warning = remainingMs <= WARNING_MS;

    return (
        <div
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 font-mono text-sm font-semibold tabular-nums',
                warning
                    ? 'border-red-300 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300'
                    : 'border-border bg-background',
            )}
        >
            <TimerIcon className="size-4" aria-hidden="true" />
            <span className="sr-only">{t('candidate.runner.timeLeft')}</span>
            <span role="timer">{formatRemaining(remainingMs)}</span>
            <span className="sr-only" aria-live="polite">
                {warning ? t('candidate.runner.timeWarning') : ''}
            </span>
        </div>
    );
}
