import { cn } from '@/lib/utils';

/**
 * Bilah progres dengan label teks untuk pembaca layar dan tampilan.
 */
export function ProgressBar({
    percent,
    label,
    className,
}: {
    percent: number;
    label: string;
    className?: string;
}) {
    const value = Math.max(0, Math.min(100, Math.round(percent)));

    return (
        <div className={cn('space-y-1', className)}>
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>{label}</span>
                <span className="tabular-nums">{value}%</span>
            </div>
            <div
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={value}
                aria-label={label}
                className="h-2 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-[width]',
                        value === 100 ? 'bg-green-600' : 'bg-primary',
                    )}
                    style={{ width: `${value}%` }}
                />
            </div>
        </div>
    );
}
