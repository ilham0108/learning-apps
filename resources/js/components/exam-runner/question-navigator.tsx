import { t } from '@/lib/i18n';
import { FlagIcon, LockedIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';

export type NavigatorItem = {
    position: number;
    answered: boolean;
    flagged: boolean;
};

type Props = {
    items: NavigatorItem[];
    current: number;
    /** Navigasi maju saja: hanya soal yang sedang dibuka yang aktif. */
    forwardOnly: boolean;
    onSelect: (position: number) => void;
};

/**
 * Daftar nomor soal. Status setiap soal dibaca dari label teks (`aria-label`
 * dan legenda), tidak hanya dari warna.
 */
export function QuestionNavigator({
    items,
    current,
    forwardOnly,
    onSelect,
}: Props) {
    return (
        <nav aria-label={t('candidate.runner.navigatorTitle')}>
            <ol className="grid grid-cols-5 gap-2 sm:grid-cols-8 lg:grid-cols-5">
                {items.map((item) => {
                    const isCurrent = item.position === current;
                    const locked = forwardOnly && !isCurrent;
                    const states = [
                        item.answered
                            ? t('candidate.runner.legend.answered')
                            : t('candidate.runner.legend.unanswered'),
                        item.flagged && t('candidate.runner.legend.flagged'),
                        locked && t('candidate.runner.legend.locked'),
                    ].filter(Boolean);

                    return (
                        <li key={item.position}>
                            <button
                                type="button"
                                onClick={() => onSelect(item.position)}
                                disabled={locked}
                                aria-current={isCurrent ? 'step' : undefined}
                                aria-label={`${t('candidate.runner.navigatorItem', { number: item.position })}, ${states.join(', ')}`}
                                className={cn(
                                    'relative flex h-10 w-full items-center justify-center rounded-md border text-sm font-medium tabular-nums transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none disabled:cursor-not-allowed',
                                    item.answered
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-background hover:bg-muted',
                                    isCurrent &&
                                        'ring-2 ring-primary ring-offset-2 ring-offset-background',
                                    locked && 'opacity-50',
                                )}
                            >
                                {item.position}
                                {item.flagged && (
                                    <FlagIcon
                                        className="absolute -top-1.5 -right-1.5 size-4 rounded-full bg-amber-400 p-0.5 text-amber-950"
                                        aria-hidden="true"
                                    />
                                )}
                            </button>
                        </li>
                    );
                })}
            </ol>

            <ul className="mt-4 grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs text-muted-foreground">
                <li className="flex items-center gap-1.5">
                    <span
                        className="size-3 rounded-sm bg-primary"
                        aria-hidden="true"
                    />
                    {t('candidate.runner.legend.answered')}
                </li>
                <li className="flex items-center gap-1.5">
                    <span
                        className="size-3 rounded-sm border bg-background"
                        aria-hidden="true"
                    />
                    {t('candidate.runner.legend.unanswered')}
                </li>
                <li className="flex items-center gap-1.5">
                    <FlagIcon
                        className="size-3 text-amber-600"
                        aria-hidden="true"
                    />
                    {t('candidate.runner.legend.flagged')}
                </li>
                {forwardOnly && (
                    <li className="flex items-center gap-1.5">
                        <LockedIcon className="size-3" aria-hidden="true" />
                        {t('candidate.runner.legend.locked')}
                    </li>
                )}
            </ul>
        </nav>
    );
}
