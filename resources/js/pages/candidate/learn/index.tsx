import { Head, Link, usePage } from '@inertiajs/react';
import { EmptyState } from '@/components/app/empty-state';
import { LearningStatusBadge } from '@/components/app/learning-badges';
import { PageHeader } from '@/components/app/page-header';
import { ProgressBar } from '@/components/app/progress-bar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { LearningIcon } from '@/lib/icons';
import learn from '@/routes/learn';
import type { ProgressSummary } from '@/types';

type Program = {
    id: string;
    name: string;
    description: string | null;
    category: string | null;
    estimated_minutes: number | null;
    due_at: string | null;
    progress: ProgressSummary;
};

export default function LearnIndex({ programs }: { programs: Program[] }) {
    const { displayTimezone } = usePage().props;

    return (
        <>
            <Head title={t('candidate.learn.title')} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('candidate.learn.title')}
                    description={t('candidate.learn.description')}
                />

                {programs.length === 0 ? (
                    <EmptyState
                        title={t('candidate.learn.empty')}
                        description={t('candidate.learn.emptyDescription')}
                    />
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {programs.map((program) => (
                            <Card key={program.id} className="flex flex-col">
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-2">
                                        <CardTitle className="flex items-center gap-2">
                                            <LearningIcon
                                                className="size-5 shrink-0 text-primary"
                                                aria-hidden="true"
                                            />
                                            {program.name}
                                        </CardTitle>
                                        <LearningStatusBadge
                                            status={program.progress.status}
                                        />
                                    </div>
                                    <CardDescription>
                                        {[
                                            program.category,
                                            program.estimated_minutes
                                                ? t('admin.programs.minutes', {
                                                      count: program.estimated_minutes,
                                                  })
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex-1 space-y-3">
                                    {program.description && (
                                        <p className="line-clamp-3 text-sm text-muted-foreground">
                                            {program.description}
                                        </p>
                                    )}
                                    <ProgressBar
                                        percent={program.progress.percent}
                                        label={t(
                                            'candidate.learn.requiredProgress',
                                            {
                                                done: program.progress
                                                    .required_completed,
                                                total: program.progress
                                                    .required_total,
                                            },
                                        )}
                                    />
                                    {program.due_at && (
                                        <p className="text-xs text-muted-foreground">
                                            {t('candidate.learn.dueAt', {
                                                date: formatDate(
                                                    program.due_at,
                                                    displayTimezone,
                                                ),
                                            })}
                                        </p>
                                    )}
                                </CardContent>
                                <CardFooter>
                                    <Button
                                        asChild
                                        className="w-full sm:w-auto"
                                    >
                                        <Link href={learn.show(program.id)}>
                                            {program.progress.status ===
                                            'not_started'
                                                ? t('candidate.learn.open')
                                                : t('candidate.learn.continue')}
                                        </Link>
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

LearnIndex.layout = {
    breadcrumbs: [{ title: t('candidate.learn.title'), href: learn.index() }],
};
