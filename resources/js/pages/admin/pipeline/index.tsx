import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/app/page-header';
import { PipelineEditor } from '@/components/campaigns/pipeline-editor';
import { Card, CardContent } from '@/components/ui/card';
import { t } from '@/lib/i18n';
import pipeline from '@/routes/admin/pipeline';
import type { PipelineStage } from '@/types';

type Props = {
    stages: PipelineStage[];
    maxStages: number;
};

export default function PipelineIndex({ stages, maxStages }: Props) {
    return (
        <>
            <Head title={t('admin.pipeline.title')} />

            <div className="flex max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.pipeline.title')}
                    description={t('admin.pipeline.description')}
                />

                <Card>
                    <CardContent>
                        <PipelineEditor
                            stages={stages}
                            editable
                            maxStages={maxStages}
                            routes={{
                                store: () => pipeline.stages.store(),
                                update: (id) => pipeline.stages.update(id),
                                move: (id) => pipeline.stages.move(id),
                                destroy: (id) => pipeline.stages.destroy(id),
                            }}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PipelineIndex.layout = {
    breadcrumbs: [{ title: t('admin.pipeline.title'), href: pipeline.index() }],
};
