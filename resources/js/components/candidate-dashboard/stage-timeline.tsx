import { t } from '@/lib/i18n';
import { CompletedIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';
import type { OwnApplication } from '@/types';

/**
 * Timeline tahap rekrutmen: Diundang → Terdaftar → tahap proses rekrutmen
 * ini → Diterima. Setiap langkah membawa label teks dan keterangan status
 * untuk pembaca layar, sehingga tidak bergantung pada warna. Lamaran yang
 * berakhir (tidak lolos, mengundurkan diri) ditampilkan sebagai catatan.
 */
export function StageTimeline({
    application,
}: {
    application: Pick<
        OwnApplication,
        'stage' | 'stage_label' | 'pipeline' | 'current_step' | 'position'
    >;
}) {
    const { stage } = application;

    if (stage === 'rejected' || stage === 'withdrawn') {
        return (
            <p className="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">
                {t('candidate.dashboard.stageEnded', {
                    stage: t(`status.recruitmentStage.${stage}`),
                })}
            </p>
        );
    }

    const steps = [
        t('status.recruitmentStage.invited'),
        t('status.recruitmentStage.registered'),
        ...application.pipeline,
        t('status.recruitmentStage.hired'),
    ];
    const current = {
        invited: 0,
        registered: 1,
        in_process: 2 + (application.current_step ?? 0),
        hired: steps.length - 1,
    }[stage];

    return (
        <div className="overflow-x-auto pb-1">
            <ol
                aria-label={t('candidate.dashboard.timelineLabel', {
                    position: application.position,
                })}
                className="flex min-w-max items-start"
            >
                {steps.map((label, index) => {
                    const done = index < current || stage === 'hired';
                    const isCurrent = index === current && stage !== 'hired';
                    const state = done
                        ? t('candidate.dashboard.stageDone')
                        : isCurrent
                          ? t('candidate.dashboard.stageCurrent')
                          : t('candidate.dashboard.stageUpcoming');

                    return (
                        <li
                            key={`${index}-${label}`}
                            aria-current={isCurrent ? 'step' : undefined}
                            className="flex w-24 flex-col items-center gap-1.5 text-center"
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
                                        index === steps.length - 1
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
                                    'px-1 text-[11px] leading-tight sm:text-xs',
                                    isCurrent
                                        ? 'font-semibold text-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {label}
                                <span className="sr-only">, {state}</span>
                            </span>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
