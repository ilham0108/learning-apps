import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { CompletedIcon } from '@/lib/icons';
import learn from '@/routes/learn';
import type { MaterialContent, MaterialType } from '@/types';

type Neighbour = { id: string; title: string } | null;

type Props = {
    program: { id: string; name: string };
    module: { title: string };
    material: MaterialContent & {
        id: string;
        title: string;
        type: MaterialType;
        is_required: boolean;
        completed_at: string | null;
    };
    previous: Neighbour;
    next: Neighbour;
};

export default function LearnMaterial({
    program,
    module,
    material,
    previous,
    next,
}: Props) {
    const { displayTimezone } = usePage().props;
    const form = useForm({});

    const complete = () =>
        form.submit(learn.material.complete([program.id, material.id]), {
            preserveScroll: true,
        });

    return (
        <>
            <Head title={material.title} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <Link
                        href={learn.show(program.id)}
                        className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        {t('candidate.learn.backToProgram')}
                    </Link>
                    <p className="text-sm text-muted-foreground">
                        {program.name} · {module.title}
                    </p>
                    <h1 className="text-xl font-semibold">{material.title}</h1>
                </div>

                <Card>
                    <CardContent>
                        <MaterialBody material={material} />
                    </CardContent>
                </Card>

                <div className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between">
                    {material.completed_at ? (
                        <p className="flex items-center gap-2 text-sm font-medium text-green-700 dark:text-green-400">
                            <CompletedIcon
                                className="size-5"
                                aria-hidden="true"
                            />
                            {t('candidate.learn.completedAt', {
                                date: formatDateTime(
                                    material.completed_at,
                                    displayTimezone,
                                ),
                            })}
                        </p>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t('candidate.learn.completeHint')}
                        </p>
                    )}
                    {!material.completed_at && (
                        <Button onClick={complete} disabled={form.processing}>
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <CompletedIcon aria-hidden="true" />
                            )}
                            {t('candidate.learn.markComplete')}
                        </Button>
                    )}
                </div>

                <nav
                    className="flex flex-col gap-2 sm:flex-row sm:justify-between"
                    aria-label={t('candidate.learn.title')}
                >
                    {previous ? (
                        <Button variant="outline" asChild>
                            <Link
                                href={learn.material([program.id, previous.id])}
                            >
                                <ArrowLeft aria-hidden="true" />
                                <span className="truncate">
                                    {previous.title}
                                </span>
                            </Link>
                        </Button>
                    ) : (
                        <span />
                    )}
                    {next && (
                        <Button variant="outline" asChild>
                            <Link href={learn.material([program.id, next.id])}>
                                <span className="truncate">{next.title}</span>
                                <ArrowRight aria-hidden="true" />
                            </Link>
                        </Button>
                    )}
                </nav>
            </div>
        </>
    );
}

function MaterialBody({ material }: { material: Props['material'] }) {
    switch (material.type) {
        case 'article':
            return (
                <article
                    className="prose prose-sm max-w-none dark:prose-invert"
                    // HTML artikel sudah disanitasi di server (HtmlSanitizer) sebelum disimpan.
                    dangerouslySetInnerHTML={{
                        __html: material.body_html ?? '',
                    }}
                />
            );
        case 'pdf':
            return (
                <div className="space-y-2">
                    <iframe
                        src={material.file_url ?? undefined}
                        title={material.title}
                        className="h-[70vh] w-full rounded-md border"
                    />
                    <FileLink
                        url={material.file_url}
                        hint={t('candidate.learn.pdfFallback')}
                    />
                </div>
            );
        case 'image':
            return (
                <img
                    src={material.file_url ?? undefined}
                    alt={material.title}
                    className="mx-auto max-h-[70vh] w-auto rounded-md"
                />
            );
        case 'video_file':
            return (
                <video
                    controls
                    preload="metadata"
                    className="w-full rounded-md"
                    src={material.file_url ?? undefined}
                >
                    {t('candidate.learn.videoUnsupported')}
                </video>
            );
        case 'video_embed':
            return (
                <div className="aspect-video w-full overflow-hidden rounded-md">
                    <iframe
                        src={material.embed_url ?? undefined}
                        title={material.title}
                        className="h-full w-full"
                        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                        allowFullScreen
                        referrerPolicy="strict-origin-when-cross-origin"
                    />
                </div>
            );
        case 'link':
            return (
                <Button asChild>
                    <a
                        href={material.external_url ?? undefined}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <ExternalLink aria-hidden="true" />
                        {t('candidate.learn.openLink')}
                    </a>
                </Button>
            );
    }
}

function FileLink({ url, hint }: { url: string | null; hint: string }) {
    if (!url) {
        return null;
    }

    return (
        <p className="text-xs text-muted-foreground">
            {hint}{' '}
            <a
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                className="text-primary hover:underline"
            >
                {t('candidate.learn.openFile')}
            </a>
        </p>
    );
}

LearnMaterial.layout = {
    breadcrumbs: [{ title: t('candidate.learn.title'), href: learn.index() }],
};
