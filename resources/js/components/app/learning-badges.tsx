import { StatusBadge } from '@/components/app/status-badge';
import type { StatusTone } from '@/components/app/status-badge';
import { t } from '@/lib/i18n';
import type { LearningProgressStatus, ProgramStatus } from '@/types';

const programTones: Record<ProgramStatus, StatusTone> = {
    draft: 'neutral',
    published: 'success',
    archived: 'warning',
};

export function ProgramStatusBadge({ status }: { status: ProgramStatus }) {
    return (
        <StatusBadge
            label={t(`status.program.${status}`)}
            tone={programTones[status]}
        />
    );
}

const learningTones: Record<LearningProgressStatus, StatusTone> = {
    not_started: 'neutral',
    in_progress: 'info',
    completed: 'success',
};

export function LearningStatusBadge({
    status,
}: {
    status: LearningProgressStatus;
}) {
    return (
        <StatusBadge
            label={t(`status.learning.${status}`)}
            tone={learningTones[status]}
        />
    );
}
