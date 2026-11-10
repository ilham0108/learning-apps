import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { LearningStatusBadge } from '@/components/app/learning-badges';
import { PageHeader } from '@/components/app/page-header';
import { ProgressBar } from '@/components/app/progress-bar';
import { StatusBadge } from '@/components/app/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    CompletedIcon,
    InProgressIcon,
    materialTypeIcons,
    NotStartedIcon,
} from '@/lib/icons';
import { cn } from '@/lib/utils';
import learn from '@/routes/learn';
import type {
    LearningProgressStatus,
    MaterialType,
    ProgressSummary,
} from '@/types';

type Props = {
    program: {
        id: string;
        name: string;
        description: string | null;
        estimated_minutes: number | null;
        due_at: string | null;
        progress: ProgressSummary;
        modules: {
            id: string;
            title: string;
            description: string | null;
            materials: {
                id: string;
                title: string;
                type: MaterialType;
                is_required: boolean;
                status: LearningProgressStatus;
            }[];
        }[];
    };
};

const statusIcons: Record<LearningProgressStatus, LucideIcon> = {
    not_started: NotStartedIcon,
    in_progress: InProgressIcon,
    completed: CompletedIcon,
};

export default function LearnShow({ program }: Props) {
    const { displayTimezone } = usePage().props;

    return (
        <>
            <Head title={program.name} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={program.name}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <LearningStatusBadge
                                status={program.progress.status}
                            />
                            {program.due_at &&
                                t('candidate.learn.dueAt', {
                                    date: formatDate(
                                        program.due_at,
                                        displayTimezone,
                                    ),
                                })}
                        </span>
                    }
                />

                {program.description && (
                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                        {program.description}
                    </p>
                )}

                <ProgressBar
                    percent={program.progress.percent}
                    label={t('candidate.learn.requiredProgress', {
                        done: program.progress.required_completed,
                        total: program.progress.required_total,
                    })}
                />

                <ol className="space-y-4">
                    {program.modules.map((module, index) => (
                        <li key={module.id}>
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {index + 1}. {module.title}
                                    </CardTitle>
                                    {module.description && (
                                        <p className="text-sm text-muted-foreground">
                                            {module.description}
                                        </p>
                                    )}
                                </CardHeader>
                                <CardContent>
                                    {module.materials.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            {t('candidate.learn.noMaterials')}
                                        </p>
                                    ) : (
                                        <ul className="divide-y rounded-lg border">
                                            {module.materials.map(
                                                (material) => {
                                                    const TypeIcon =
                                                        materialTypeIcons[
                                                            material.type
                                                        ];
                                                    const StatusIcon =
                                                        statusIcons[
                                                            material.status
                                                        ];

                                                    return (
                                                        <li key={material.id}>
                                                            <Link
                                                                href={learn.material(
                                                                    [
                                                                        program.id,
                                                                        material.id,
                                                                    ],
                                                                )}
                                                                className="flex items-center gap-3 p-3 hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                            >
                                                                <StatusIcon
                                                                    className={cn(
                                                                        'size-5 shrink-0',
                                                                        material.status ===
                                                                            'completed'
                                                                            ? 'text-green-600'
                                                                            : 'text-muted-foreground',
                                                                    )}
                                                                    aria-hidden="true"
                                                                />
                                                                <span className="min-w-0 flex-1">
                                                                    <span className="block truncate text-sm font-medium">
                                                                        {
                                                                            material.title
                                                                        }
                                                                    </span>
                                                                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                                                        <TypeIcon
                                                                            className="size-3.5"
                                                                            aria-hidden="true"
                                                                        />
                                                                        {t(
                                                                            `status.materialType.${material.type}`,
                                                                        )}
                                                                    </span>
                                                                </span>
                                                                <span className="flex shrink-0 flex-col items-end gap-1">
                                                                    <StatusBadge
                                                                        label={t(
                                                                            `status.learning.${material.status}`,
                                                                        )}
                                                                        tone={
                                                                            material.status ===
                                                                            'completed'
                                                                                ? 'success'
                                                                                : material.status ===
                                                                                    'in_progress'
                                                                                  ? 'info'
                                                                                  : 'neutral'
                                                                        }
                                                                    />
                                                                    {!material.is_required && (
                                                                        <span className="text-xs text-muted-foreground">
                                                                            {t(
                                                                                'candidate.learn.optional',
                                                                            )}
                                                                        </span>
                                                                    )}
                                                                </span>
                                                            </Link>
                                                        </li>
                                                    );
                                                },
                                            )}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>
                        </li>
                    ))}
                </ol>
            </div>
        </>
    );
}

LearnShow.layout = {
    breadcrumbs: [{ title: t('candidate.learn.title'), href: learn.index() }],
};
