import { Head, usePage } from '@inertiajs/react';
import { EmptyState } from '@/components/app/empty-state';
import { OptionSelect } from '@/components/app/option-select';
import { PageHeader } from '@/components/app/page-header';
import { Pagination } from '@/components/app/pagination';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatDateTime } from '@/lib/format';
import type { TranslationKey } from '@/lib/i18n';
import { t } from '@/lib/i18n';
import { AuditIcon } from '@/lib/icons';
import auditLog from '@/routes/admin/audit-log';
import type { Paginated, Role } from '@/types';

type LogRow = {
    action: string;
    actor: { name: string; role: Role } | null;
    subject: string | null;
    reason: string | null;
    properties: string | null;
    ip_address: string | null;
    created_at: string | null;
};

type Filters = { action: string | null; actor: string | null };

type Props = {
    logs: Paginated<LogRow>;
    filters: Filters;
    options: { actions: string[]; actors: { id: string; name: string }[] };
};

/** Label tindakan dari kamus; tindakan baru yang belum diterjemahkan tampil apa adanya. */
function actionLabel(action: string): string {
    const key = `admin.auditLog.actions.${action}` as TranslationKey;
    const label = t(key);

    return label === key ? action : label;
}

export default function AuditLogIndex({ logs, filters, options }: Props) {
    const { displayTimezone } = usePage().props;
    const apply = useQueryFilters(auditLog.index().url, filters);

    return (
        <>
            <Head title={t('admin.auditLog.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.auditLog.title')}
                    description={t('admin.auditLog.description')}
                />

                <div className="flex flex-col gap-3 sm:flex-row">
                    <OptionSelect
                        id="action"
                        value={filters.action}
                        onChange={(action) => apply({ action })}
                        options={options.actions.map((action) => ({
                            value: action,
                            label: actionLabel(action),
                        }))}
                        allowAll
                        ariaLabel={t('admin.auditLog.filters.action')}
                        className="sm:w-72"
                    />
                    <OptionSelect
                        id="actor"
                        value={filters.actor}
                        onChange={(actor) => apply({ actor })}
                        options={options.actors.map((actor) => ({
                            value: actor.id,
                            label: actor.name,
                        }))}
                        allowAll
                        ariaLabel={t('admin.auditLog.filters.actor')}
                        className="sm:w-64"
                    />
                </div>

                {logs.data.length === 0 ? (
                    <EmptyState
                        icon={AuditIcon}
                        title={t('admin.auditLog.empty')}
                    />
                ) : (
                    <ol className="divide-y rounded-xl border">
                        {logs.data.map((log, index) => (
                            <li
                                key={`${logs.meta.current_page}-${index}`}
                                className="space-y-1.5 p-4 text-sm"
                            >
                                <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <p>
                                        <span className="font-medium">
                                            {log.actor?.name ??
                                                t('admin.auditLog.system')}
                                        </span>
                                        {log.actor && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                ({t(`roles.${log.actor.role}`)})
                                            </span>
                                        )}{' '}
                                        {actionLabel(log.action)}
                                        {log.subject && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · {log.subject}
                                            </span>
                                        )}
                                    </p>
                                    <p className="text-xs text-muted-foreground tabular-nums">
                                        {formatDateTime(
                                            log.created_at,
                                            displayTimezone,
                                        )}
                                        {log.ip_address &&
                                            ` · ${t('admin.auditLog.ip', { ip: log.ip_address })}`}
                                    </p>
                                </div>
                                {log.reason && (
                                    <p>
                                        <span className="text-muted-foreground">
                                            {t('admin.auditLog.reason')}:
                                        </span>{' '}
                                        {log.reason}
                                    </p>
                                )}
                                {log.properties && (
                                    <details>
                                        <summary className="cursor-pointer text-xs text-primary">
                                            {t('admin.auditLog.details')}
                                        </summary>
                                        <pre className="mt-2 overflow-x-auto rounded-md bg-muted p-3 text-xs">
                                            {log.properties}
                                        </pre>
                                    </details>
                                )}
                            </li>
                        ))}
                    </ol>
                )}

                <Pagination
                    meta={logs.meta}
                    onPageChange={(page) => apply({ page })}
                />
            </div>
        </>
    );
}

AuditLogIndex.layout = {
    breadcrumbs: [{ title: t('admin.auditLog.title'), href: auditLog.index() }],
};
