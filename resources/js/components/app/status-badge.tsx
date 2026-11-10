import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';
import type {
    CampaignStatus,
    EvaluationStatus,
    RecruitmentStage,
} from '@/types';

export type StatusTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

const toneClasses: Record<StatusTone, string> = {
    neutral:
        'border-neutral-200 bg-neutral-100 text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200',
    info: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300',
    success:
        'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950 dark:text-green-300',
    warning:
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    danger: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
};

/**
 * Badge status. Status selalu ditampilkan dengan label teks, tidak hanya warna.
 */
export function StatusBadge({
    label,
    tone = 'neutral',
    className,
}: {
    label: string;
    tone?: StatusTone;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex w-fit shrink-0 items-center rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                toneClasses[tone],
                className,
            )}
        >
            {label}
        </span>
    );
}

const stageTones: Record<RecruitmentStage, StatusTone> = {
    invited: 'neutral',
    registered: 'info',
    under_review: 'warning',
    shortlisted: 'info',
    rejected: 'danger',
    hired: 'success',
    withdrawn: 'neutral',
};

export function RecruitmentStageBadge({ stage }: { stage: RecruitmentStage }) {
    return (
        <StatusBadge
            label={t(`status.recruitmentStage.${stage}`)}
            tone={stageTones[stage]}
        />
    );
}

const campaignTones: Record<CampaignStatus, StatusTone> = {
    draft: 'neutral',
    open: 'success',
    closed: 'warning',
};

export function CampaignStatusBadge({ status }: { status: CampaignStatus }) {
    return (
        <StatusBadge
            label={t(`status.campaign.${status}`)}
            tone={campaignTones[status]}
        />
    );
}

export function AccountStatusBadge({
    isActive,
    isActivated,
}: {
    isActive: boolean;
    isActivated: boolean;
}) {
    if (!isActive) {
        return (
            <StatusBadge label={t('status.account.inactive')} tone="danger" />
        );
    }

    return isActivated ? (
        <StatusBadge label={t('status.account.active')} tone="success" />
    ) : (
        <StatusBadge label={t('status.account.notActivated')} tone="warning" />
    );
}

export function EvaluationStatusBadge({
    status,
}: {
    status: EvaluationStatus;
}) {
    return (
        <StatusBadge
            label={t(`status.evaluation.${status}`)}
            tone={status === 'final' ? 'success' : 'neutral'}
        />
    );
}
