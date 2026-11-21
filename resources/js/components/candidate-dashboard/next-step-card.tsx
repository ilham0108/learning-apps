import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { t } from '@/lib/i18n';
import {
    CandidateIcon,
    CompletedIcon,
    ExamIcon,
    GradingIcon,
    LearningIcon,
    TimerIcon,
} from '@/lib/icons';
import type { NextStep } from '@/lib/next-step';
import { formatDuration } from '@/lib/next-step';
import { cn } from '@/lib/utils';
import attempts from '@/routes/attempts';
import candidateProfile from '@/routes/candidate-profile';
import candidateExams from '@/routes/exams';
import learn from '@/routes/learn';

const DAY_MS = 24 * 60 * 60 * 1000;

/** "2 hari 4 jam", "3 jam 15 menit", "kurang dari 1 menit". */
export function durationText(ms: number): string {
    const { days, hours, minutes } = formatDuration(ms);
    const parts = [
        days > 0 && t('candidate.dashboard.duration.days', { count: days }),
        hours > 0 && t('candidate.dashboard.duration.hours', { count: hours }),
        days === 0 &&
            minutes > 0 &&
            t('candidate.dashboard.duration.minutes', { count: minutes }),
    ].filter(Boolean);

    return parts.length > 0
        ? parts.join(' ')
        : t('candidate.dashboard.duration.underMinute');
}

/** Hitung mundur jadwal tutup; berubah warna dan kalimat jika kurang dari 24 jam. */
export function ClosingCountdown({
    closesAt,
    now,
    className,
}: {
    closesAt: string | null;
    now: number;
    className?: string;
}) {
    if (closesAt === null) {
        return null;
    }

    const remaining = Date.parse(closesAt) - now;
    const urgent = remaining < DAY_MS;

    return (
        <p
            className={cn(
                'inline-flex items-center gap-1.5 text-sm font-medium tabular-nums',
                urgent ? 'text-red-700 dark:text-red-400' : 'text-foreground',
                className,
            )}
        >
            <TimerIcon className="size-4" aria-hidden="true" />
            {t('candidate.dashboard.closesIn', {
                time: durationText(remaining),
            })}
            {urgent && (
                <span className="sr-only">
                    , {t('candidate.dashboard.closesSoon')}
                </span>
            )}
        </p>
    );
}

type View = {
    icon: LucideIcon;
    title: string;
    description: string;
    action?: { label: string; href: ReturnType<typeof learn.index> };
    closesAt: string | null;
};

function viewOf(step: NextStep): View {
    switch (step.kind) {
        case 'continue_exam':
            return {
                icon: TimerIcon,
                title: t('candidate.dashboard.nextStep.continue_exam.title', {
                    exam: step.exam.title,
                }),
                description: t(
                    'candidate.dashboard.nextStep.continue_exam.description',
                ),
                action: {
                    label: t(
                        'candidate.dashboard.nextStep.continue_exam.action',
                    ),
                    href: step.exam.active_attempt
                        ? attempts.show(step.exam.active_attempt)
                        : candidateExams.show(step.exam.id),
                },
                closesAt: step.exam.closes_at,
            };
        case 'complete_profile':
            return {
                icon: CandidateIcon,
                title: t('candidate.dashboard.nextStep.complete_profile.title'),
                description: t(
                    'candidate.dashboard.nextStep.complete_profile.description',
                    { percent: step.percent },
                ),
                action: {
                    label: t(
                        'candidate.dashboard.nextStep.complete_profile.action',
                    ),
                    href: candidateProfile.edit(),
                },
                closesAt: null,
            };
        case 'start_exam':
            return {
                icon: ExamIcon,
                title: t('candidate.dashboard.nextStep.start_exam.title', {
                    exam: step.exam.title,
                }),
                description: t(
                    'candidate.dashboard.nextStep.start_exam.description',
                    {
                        count: Math.max(
                            0,
                            step.exam.max_attempts - step.exam.attempts_used,
                        ),
                    },
                ),
                action: {
                    label: t('candidate.dashboard.nextStep.start_exam.action'),
                    href: candidateExams.show(step.exam.id),
                },
                closesAt: step.exam.closes_at,
            };
        case 'unlock_exam':
            return {
                icon: LearningIcon,
                title: t('candidate.dashboard.nextStep.unlock_exam.title', {
                    exam: step.exam.title,
                }),
                description: t(
                    'candidate.dashboard.nextStep.unlock_exam.description',
                    {
                        material: step.material.title,
                        program: step.material.program.name,
                    },
                ),
                action: {
                    label: t('candidate.dashboard.nextStep.unlock_exam.action'),
                    href: learn.material([
                        step.material.program.id,
                        step.material.id,
                    ]),
                },
                closesAt: step.exam.closes_at,
            };
        case 'continue_learning':
            return {
                icon: LearningIcon,
                title: t(
                    'candidate.dashboard.nextStep.continue_learning.title',
                ),
                description: t(
                    'candidate.dashboard.nextStep.continue_learning.description',
                    {
                        material: step.material.title,
                        program: step.material.program.name,
                    },
                ),
                action: {
                    label: t(
                        'candidate.dashboard.nextStep.continue_learning.action',
                    ),
                    href: learn.material([
                        step.material.program.id,
                        step.material.id,
                    ]),
                },
                closesAt: null,
            };
        case 'awaiting_grading':
            return {
                icon: GradingIcon,
                title: t(
                    'candidate.dashboard.nextStep.awaiting_grading.title',
                    {
                        exam: step.exam.title,
                    },
                ),
                description: t(
                    'candidate.dashboard.nextStep.awaiting_grading.description',
                ),
                action: {
                    label: t(
                        'candidate.dashboard.nextStep.awaiting_grading.action',
                    ),
                    href: candidateExams.show(step.exam.id),
                },
                closesAt: null,
            };
        case 'done':
            return {
                icon: CompletedIcon,
                title: t('candidate.dashboard.nextStep.done.title'),
                description: t('candidate.dashboard.nextStep.done.description'),
                closesAt: null,
            };
    }
}

/** Kartu utama dashboard: satu tindakan paling penting saat ini. */
export function NextStepCard({ step, now }: { step: NextStep; now: number }) {
    const view = viewOf(step);
    const Icon = view.icon;
    const highlighted = step.kind !== 'done';

    return (
        <Card
            className={cn(
                'overflow-hidden',
                highlighted &&
                    'border-primary/40 bg-gradient-to-br from-primary/10 via-background to-background',
            )}
        >
            <CardContent className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-start gap-4">
                    <div
                        className={cn(
                            'flex size-12 shrink-0 items-center justify-center rounded-xl',
                            highlighted
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400',
                        )}
                    >
                        <Icon className="size-6" aria-hidden="true" />
                    </div>
                    <div className="min-w-0 space-y-1.5">
                        <p className="text-xs font-semibold tracking-wide text-primary uppercase">
                            {t('candidate.dashboard.nextStep.label')}
                        </p>
                        <h2 className="text-lg font-semibold sm:text-xl">
                            {view.title}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {view.description}
                        </p>
                        <ClosingCountdown closesAt={view.closesAt} now={now} />
                    </div>
                </div>
                {view.action && (
                    <Button asChild size="lg" className="shrink-0">
                        <Link href={view.action.href}>
                            {view.action.label}
                            <ArrowRight aria-hidden="true" />
                        </Link>
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}
