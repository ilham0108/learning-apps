import { StatusBadge } from '@/components/app/status-badge';
import type { StatusTone } from '@/components/app/status-badge';
import { t } from '@/lib/i18n';
import type {
    AttemptResult,
    AttemptStatus,
    ExamProgress,
    ExamStatus,
    GradingStatus,
    QuestionStatus,
} from '@/types';

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

const attemptTones: Record<AttemptStatus, StatusTone> = {
    in_progress: 'info',
    submitted: 'neutral',
    auto_submitted: 'warning',
    expired: 'danger',
};

export function AttemptStatusBadge({ status }: { status: AttemptStatus }) {
    return (
        <StatusBadge
            label={t(`status.attempt.${status}`)}
            tone={attemptTones[status]}
        />
    );
}

export function GradingStatusBadge({ status }: { status: GradingStatus }) {
    return (
        <StatusBadge
            label={t(`status.grading.${status}`)}
            tone={status === 'graded' ? 'success' : 'warning'}
        />
    );
}

export function AttemptResultBadge({ result }: { result: AttemptResult }) {
    return (
        <StatusBadge
            label={t(`status.result.${result}`)}
            tone={result === 'passed' ? 'success' : 'danger'}
        />
    );
}

const progressTones: Record<ExamProgress, StatusTone> = {
    not_started: 'neutral',
    in_progress: 'info',
    awaiting_grading: 'warning',
    completed: 'success',
};

export function ExamProgressBadge({ progress }: { progress: ExamProgress }) {
    return (
        <StatusBadge
            label={t(`status.examProgress.${progress}`)}
            tone={progressTones[progress]}
        />
    );
}
