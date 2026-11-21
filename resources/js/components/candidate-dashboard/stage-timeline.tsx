import { t } from '@/lib/i18n';
import { CompletedIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';
import type { RecruitmentStage } from '@/types';

/** Urutan tahap yang dilalui lamaran yang berjalan normal. */
const path: RecruitmentStage[] = [
    'invited',
    'registered',
    'under_review',
    'shortlisted',
    'hired',
];

/**
 * Timeline tahap rekrutmen. Setiap langkah membawa label teks dan keterangan
 * status untuk pembaca layar, sehingga tidak bergantung pada warna.
 * Lamaran yang berakhir (tidak lolos, mengundurkan diri) ditampilkan sebagai
 * catatan, karena tidak punya posisi di jalur normal.
 */
export function StageTimeline({
    stage,
    position,
}: {
    stage: RecruitmentStage;
    position: string;
}) {
    const ended = stage === 'rejected' || stage === 'withdrawn';
    const current = path.indexOf(stage);

    if (ended) {
        return (
            <p className="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">
                {t('candidate.dashboard.stageEnded', {
                    stage: t(`status.recruitmentStage.${stage}`),
                })}
            </p>
        );
    }

    return (
        <ol
            aria-label={t('candidate.dashboard.timelineLabel', { position })}
            className="grid grid-cols-5 gap-1"
        >
            {path.map((step, index) => {
                const done = index < current || stage === 'hired';
                const isCurrent = index === current && stage !== 'hired';
                const state = done
                    ? t('candidate.dashboard.stageDone')
                    : isCurrent
                      ? t('candidate.dashboard.stageCurrent')
                      : t('candidate.dashboard.stageUpcoming');

                return (
                    <li
                        key={step}
                        aria-current={isCurrent ? 'step' : undefined}
                        className="flex flex-col items-center gap-1.5 text-center"
                    >
                        <div className="flex w-full items-center">
                            <span
                                className={cn(
                                    'h-0.5 flex-1',
                                    index === 0
                                        ? 'bg-transparent'
                                        : index <= current
                                          ? 'bg-primary'
                                          : 'bg-border',
                                )}
                                aria-hidden="true"
                            />
                            <span
                                className={cn(
                                    'flex size-6 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold',
                                    done &&
                                        'border-primary bg-primary text-primary-foreground',
                                    isCurrent &&
                                        'border-primary bg-background text-primary ring-4 ring-primary/15',
                                    !done &&
                                        !isCurrent &&
                                        'border-border bg-background text-muted-foreground',
                                )}
                                aria-hidden="true"
                            >
                                {done ? (
                                    <CompletedIcon className="size-4" />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            <span
                                className={cn(
                                    'h-0.5 flex-1',
                                    index === path.length - 1
                                        ? 'bg-transparent'
                                        : index < current
                                          ? 'bg-primary'
                                          : 'bg-border',
                                )}
                                aria-hidden="true"
                            />
                        </div>
                        <span
                            className={cn(
                                'text-[11px] leading-tight sm:text-xs',
                                isCurrent
                                    ? 'font-semibold text-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {t(`status.recruitmentStage.${step}`)}
                            <span className="sr-only">, {state}</span>
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
