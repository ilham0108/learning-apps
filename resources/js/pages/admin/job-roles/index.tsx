import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { FormField } from '@/components/app/form-field';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { TranslationKey } from '@/lib/i18n';
import { t } from '@/lib/i18n';
import jobRolesRoutes from '@/routes/admin/job-roles';
import type { Permission } from '@/types';

type Group =
    | 'recruitment'
    | 'candidates'
    | 'learning'
    | 'exams'
    | 'reports'
    | 'system';

type JobRole = {
    id: string;
    name: string;
    description: string | null;
    is_system: boolean;
    users_count: number;
    permissions: Permission[];
    editable: boolean;
};

type PermissionOption = {
    value: Permission;
    group: Group;
    grantable: boolean;
};

type Props = {
    jobRoles: JobRole[];
    permissions: PermissionOption[];
};

const groups: Group[] = [
    'recruitment',
    'candidates',
    'learning',
    'exams',
    'reports',
    'system',
];

const permissionLabel = (permission: Permission) =>
    t(`admin.jobRoles.permissions.${permission}` as TranslationKey);

function RoleDialog({
    role,
    permissions,
    onClose,
}: {
    role: JobRole | null;
    permissions: PermissionOption[];
    onClose: () => void;
}) {
    const form = useForm<{
        name: string;
        description: string;
        permissions: Permission[];
    }>({
        name: role?.name ?? '',
        description: role?.description ?? '',
        permissions: role?.permissions ?? [],
    });

    const toggle = (permission: Permission, checked: boolean) =>
        form.setData(
            'permissions',
            checked
                ? [...form.data.permissions, permission]
                : form.data.permissions.filter((item) => item !== permission),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(
            role ? jobRolesRoutes.update(role.id) : jobRolesRoutes.store(),
            { preserveScroll: true, onSuccess: onClose },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {role
                            ? t('admin.jobRoles.editTitle', { name: role.name })
                            : t('admin.jobRoles.createTitle')}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="role-name"
                            label={t('admin.jobRoles.fields.name')}
                            error={form.errors.name}
                        >
                            <Input
                                id="role-name"
                                required
                                maxLength={100}
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="role-description"
                            label={t('admin.jobRoles.fields.description')}
                            error={form.errors.description}
                            optional
                        >
                            <Textarea
                                id="role-description"
                                rows={2}
                                maxLength={500}
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </div>

                    {form.errors.permissions && (
                        <p className="text-sm text-red-700 dark:text-red-400">
                            {form.errors.permissions}
                        </p>
                    )}

                    {groups.map((group) => (
                        <fieldset key={group} className="space-y-2">
                            <legend className="text-sm font-semibold">
                                {t(`admin.jobRoles.groups.${group}`)}
                            </legend>
                            {permissions
                                .filter((option) => option.group === group)
                                .map((option) => {
                                    const checked =
                                        form.data.permissions.includes(
                                            option.value,
                                        );
                                    // Hak yang tidak dimiliki tidak bisa diberikan; yang sudah ada tetap bisa dicabut.
                                    const locked =
                                        !option.grantable && !checked;

                                    return (
                                        <label
                                            key={option.value}
                                            className="flex items-start gap-2 text-sm"
                                            title={
                                                locked
                                                    ? t(
                                                          'admin.jobRoles.notGrantable',
                                                      )
                                                    : undefined
                                            }
                                        >
                                            <input
                                                type="checkbox"
                                                checked={checked}
                                                disabled={locked}
                                                onChange={(event) =>
                                                    toggle(
                                                        option.value,
                                                        event.target.checked,
                                                    )
                                                }
                                                className="mt-0.5 size-4 shrink-0 accent-primary disabled:opacity-50"
                                            />
                                            <span
                                                className={
                                                    locked
                                                        ? 'text-muted-foreground'
                                                        : undefined
                                                }
                                            >
                                                {permissionLabel(option.value)}
                                                {locked && (
                                                    <span className="sr-only">
                                                        ,{' '}
                                                        {t(
                                                            'admin.jobRoles.notGrantable',
                                                        )}
                                                    </span>
                                                )}
                                            </span>
                                        </label>
                                    );
                                })}
                        </fieldset>
                    ))}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                {t('common.cancel')}
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function JobRolesIndex({ jobRoles, permissions }: Props) {
    const [editing, setEditing] = useState<JobRole | 'new' | null>(null);
    const [deleting, setDeleting] = useState<JobRole | null>(null);
    const [processing, setProcessing] = useState(false);

    return (
        <>
            <Head title={t('admin.jobRoles.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.jobRoles.title')}
                    description={t('admin.jobRoles.description')}
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden="true" />
                            {t('admin.jobRoles.create')}
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    {jobRoles.map((role) => (
                        <Card key={role.id}>
                            <CardHeader className="flex-row items-start justify-between gap-2">
                                <div className="space-y-1">
                                    <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                        {role.name}
                                        {role.is_system && (
                                            <StatusBadge
                                                label={t(
                                                    'admin.jobRoles.system',
                                                )}
                                            />
                                        )}
                                    </CardTitle>
                                    <p className="text-xs text-muted-foreground">
                                        {t('admin.jobRoles.usersCount', {
                                            count: role.users_count,
                                        })}{' '}
                                        ·{' '}
                                        {t('admin.jobRoles.permissionsCount', {
                                            count: role.permissions.length,
                                        })}
                                    </p>
                                </div>
                                <div className="flex gap-1">
                                    {role.editable && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setEditing(role)}
                                        >
                                            <Pencil aria-hidden="true" />
                                            {t('common.edit')}
                                            <span className="sr-only">
                                                : {role.name}
                                            </span>
                                        </Button>
                                    )}
                                    {!role.is_system &&
                                        role.users_count === 0 && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                aria-label={`${t('admin.jobRoles.delete')}: ${role.name}`}
                                                onClick={() =>
                                                    setDeleting(role)
                                                }
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        )}
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                {role.description && (
                                    <p className="text-muted-foreground">
                                        {role.description}
                                    </p>
                                )}
                                {!role.editable && (
                                    <p className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                                        {t('admin.jobRoles.ownRoleNotice')}
                                    </p>
                                )}
                                <ul className="list-disc space-y-0.5 pl-5">
                                    {role.permissions.map((permission) => (
                                        <li key={permission}>
                                            {permissionLabel(permission)}
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>

            {editing && (
                <RoleDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    role={editing === 'new' ? null : editing}
                    permissions={permissions}
                    onClose={() => setEditing(null)}
                />
            )}

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={t('admin.jobRoles.deleteTitle', {
                    name: deleting?.name ?? '',
                })}
                description={t('admin.jobRoles.deleteDescription')}
                confirmLabel={t('admin.jobRoles.delete')}
                destructive
                processing={processing}
                onConfirm={() =>
                    deleting &&
                    router.visit(jobRolesRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false);
                            setDeleting(null);
                        },
                    })
                }
            />
        </>
    );
}

JobRolesIndex.layout = {
    breadcrumbs: [
        { title: t('admin.jobRoles.title'), href: jobRolesRoutes.index() },
    ],
};
