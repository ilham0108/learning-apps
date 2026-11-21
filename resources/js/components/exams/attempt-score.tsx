import {
    AttemptResultBadge,
    GradingStatusBadge,
} from '@/components/app/exam-badges';
import { t } from '@/lib/i18n';
import type { AttemptSummary } from '@/types';

/** Nilai attempt untuk HR: nilai sementara ditandai jelas selama masih ada jawaban belum dinilai. */
export function AttemptScore({ attempt }: { attempt: AttemptSummary }) {
    if (attempt.status === 'in_progress') {
        return <span className="text-muted-foreground">—</span>;
    }

    const pending = attempt.grading_status === 'pending_manual';

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <span className="font-medium tabular-nums">
                {pending
                    ? t('admin.attempts.provisional', {
                          score: attempt.score_percent ?? '0.00',
                      })
                    : attempt.score_percent}
            </span>
            {pending ? (
                <GradingStatusBadge status="pending_manual" />
            ) : (
                attempt.result && <AttemptResultBadge result={attempt.result} />
            )}
        </div>
    );
}
