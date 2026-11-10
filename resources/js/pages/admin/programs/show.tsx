import { Head } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import { ProgramStatusBadge } from '@/components/app/learning-badges';
import { PageHeader } from '@/components/app/page-header';
import { AssignmentPanel } from '@/components/programs/assignment-panel';
import type { AssignOptions } from '@/components/programs/assignment-panel';
import { ContentBuilder } from '@/components/programs/content-builder';
import { ProgramFormDialog } from '@/components/programs/program-form-dialog';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { t } from '@/lib/i18n';
import programs from '@/routes/admin/programs';
import type { ProgramAssignmentRow, ProgramDetail } from '@/types';

type Props = {
    program: ProgramDetail;
    assignments: ProgramAssignmentRow[];
    assignOptions: AssignOptions;
};

export default function ProgramShow({
    program,
    assignments,
    assignOptions,
}: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const meta = [
        program.category,
        program.target_position?.name,
        program.estimated_minutes
            ? t('admin.programs.minutes', { count: program.estimated_minutes })
            : null,
    ].filter(Boolean);

    return (
        <>
            <Head title={program.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={program.name}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <ProgramStatusBadge status={program.status} />
                            {meta.length > 0 && <span>{meta.join(' · ')}</span>}
                        </span>
                    }
                    actions={
                        <Button
                            variant="outline"
                            onClick={() => setEditOpen(true)}
                        >
                            <Pencil aria-hidden="true" />
                            {t('common.edit')}
                        </Button>
                    }
                />

                {program.description && (
                    <p className="max-w-3xl text-sm whitespace-pre-line">
                        {program.description}
                    </p>
                )}

                <ContentBuilder program={program} />
                <Separator />
                <AssignmentPanel
                    program={program}
                    assignments={assignments}
                    options={assignOptions}
                />
            </div>

            {editOpen && (
                <ProgramFormDialog
                    program={program}
                    positions={assignOptions.positions}
                    onClose={() => setEditOpen(false)}
                />
            )}
        </>
    );
}

ProgramShow.layout = {
    breadcrumbs: [{ title: t('admin.programs.title'), href: programs.index() }],
};
