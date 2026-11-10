import { StatusBadge } from '@/components/app/status-badge';
import type { StatusTone } from '@/components/app/status-badge';
import { t } from '@/lib/i18n';
import type { ExamStatus, QuestionStatus } from '@/types';

const examTones: Record<ExamStatus, StatusTone> = {
    draft: 'neutral',
    published: 'success',
    archived: 'warning',
};

export function ExamStatusBadge({ status }: { status: ExamStatus }) {
    return (
        <StatusBadge
            label={t(`status.exam.${status}`)}
            tone={examTones[status]}
        />
    );
}

export function QuestionStatusBadge({ status }: { status: QuestionStatus }) {
    return (
        <StatusBadge
            label={t(`status.question.${status}`)}
            tone={status === 'active' ? 'success' : 'warning'}
        />
    );
}

export function LockedBadge() {
    return <StatusBadge label={t('admin.exams.lockedBadge')} tone="info" />;
}
