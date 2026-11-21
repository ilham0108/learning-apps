import type { LucideIcon } from 'lucide-react';
import type { SaveStatus } from '@/lib/autosave-queue';
import { t } from '@/lib/i18n';
import { OfflineIcon, RetryIcon, SavedIcon, SavingIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';

const icons: Record<SaveStatus, LucideIcon | null> = {
    idle: null,
    saving: SavingIcon,
    saved: SavedIcon,
    retrying: RetryIcon,
    offline: OfflineIcon,
};

const tones: Record<SaveStatus, string> = {
    idle: 'text-muted-foreground',
    saving: 'text-muted-foreground',
    saved: 'text-green-700 dark:text-green-400',
    retrying: 'text-amber-700 dark:text-amber-400',
    offline: 'text-red-700 dark:text-red-400',
};

/** Status simpan selalu berupa teks, tidak hanya ikon atau warna. */
export function SaveIndicator({ status }: { status: SaveStatus }) {
    const Icon = icons[status];

    return (
        <p
            role="status"
            aria-live="polite"
            className={cn(
                'inline-flex items-center gap-1.5 text-xs font-medium',
                tones[status],
            )}
        >
            {Icon && (
                <Icon
                    className={cn(
                        'size-4 shrink-0',
                        status === 'saving' && 'animate-spin',
                    )}
                    aria-hidden="true"
                />
            )}
            {t(`candidate.runner.save.${status}`)}
        </p>
    );
}
