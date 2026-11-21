import { Head, router, useForm, usePage } from '@inertiajs/react';
import { KeyRound, Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { FormField } from '@/components/app/form-field';
import { OptionSelect } from '@/components/app/option-select';
import { PageHeader } from '@/components/app/page-header';
import { AccountStatusBadge } from '@/components/app/status-badge';
import { ActivationLinkDialog } from '@/components/candidates/account-actions';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { StaffIcon } from '@/lib/icons';
import staffRoutes from '@/routes/admin/staff';

type Option = { id: string; name: string };

type StaffRow = {
    id: string;
    name: string;
    email: string;
    job_role: Option | null;
    is_active: boolean;
    is_activated: boolean;
    last_login_at: string | null;
};

type Props = { staff: StaffRow[]; jobRoles: Option[] };

function CreateDialog({
    open,
    onOpenChange,
    jobRoles,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    jobRoles: Option[];
}) {
    const form = useForm({ name: '', email: '', job_role: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(staffRoutes.store(), {
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('admin.staff.createTitle')}</DialogTitle>
                    <DialogDescription>
                        {t('admin.staff.createDescription')}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <FormField
                        id="staff-name"
                        label={t('admin.staff.fields.name')}
                        error={form.errors.name}
                    >
                        <Input
                            id="staff-name"
                            required
                            maxLength={255}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="staff-email"
                        label={t('admin.staff.fields.email')}
                        error={form.errors.email}
                    >
                        <Input
                            id="staff-email"
                            type="email"
                            required
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="staff-role"
                        label={t('admin.staff.fields.jobRole')}
                        error={form.errors.job_role}
                    >
                        <OptionSelect
                            id="staff-role"
                            value={form.data.job_role || null}
                            onChange={(value) =>
                                form.setData('job_role', value ?? '')
                            }
                            options={jobRoles.map((role) => ({
                                value: role.id,
                                label: role.name,
                            }))}
                            placeholder={t('candidate.profile.select')}
                            invalid={form.errors.job_role !== undefined}
                        />
                    </FormField>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                {t('common.cancel')}
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {t('admin.staff.create')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditDialog({
    member,
    jobRoles,
    onClose,
}: {
    member: StaffRow;
    jobRoles: Option[];
    onClose: () => void;
}) {
    const form = useForm({
        job_role: member.job_role?.id ?? '',
        is_active: member.is_active,
        reason: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(staffRoutes.update(member.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('admin.staff.editTitle', { name: member.name })}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <FormField
                        id="edit-role"
                        label={t('admin.staff.fields.jobRole')}
                        error={form.errors.job_role}
                    >
                        <OptionSelect
                            id="edit-role"
                            value={form.data.job_role || null}
                            onChange={(value) =>
                                form.setData('job_role', value ?? '')
                            }
                            options={jobRoles.map((role) => ({
                                value: role.id,
                                label: role.name,
                            }))}
                            invalid={form.errors.job_role !== undefined}
                        />
                    </FormField>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.checked)
                            }
                            className="size-4 accent-primary"
                        />
                        {t('admin.staff.fields.active')}
                    </label>
                    <FormField
                        id="edit-reason"
                        label={t('admin.staff.fields.reason')}
                        error={form.errors.reason}
                        optional
                    >
                        <Input
                            id="edit-reason"
                            maxLength={500}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </FormField>
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

export default function StaffIndex({ staff, jobRoles }: Props) {
    const page = usePage();
    const { auth, displayTimezone } = page.props;
    const { flash } = page;
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<StaffRow | null>(null);
    const [link, setLink] = useState<string | null>(null);

    // Tautan aktivasi hanya dikirim sekali lewat flash.
    useEffect(() => {
        if (flash.activationLink) {
            setLink(flash.activationLink);
        }
    }, [flash.activationLink]);

    return (
        <>
            <Head title={t('admin.staff.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('admin.staff.title')}
                    description={t('admin.staff.description')}
                    actions={
                        <Button onClick={() => setCreateOpen(true)}>
                            <Plus aria-hidden="true" />
                            {t('admin.staff.create')}
                        </Button>
                    }
                />

                {staff.length === 0 ? (
                    <EmptyState
                        icon={StaffIcon}
                        title={t('admin.staff.empty')}
                    />
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {staff.map((member) => {
                            const isSelf = member.id === auth.user.id;

                            return (
                                <li
                                    key={member.id}
                                    className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">
                                                {member.name}
                                            </p>
                                            <AccountStatusBadge
                                                isActive={member.is_active}
                                                isActivated={
                                                    member.is_activated
                                                }
                                            />
                                        </div>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {member.email} ·{' '}
                                            {member.job_role?.name ??
                                                t('admin.staff.noJobRole')}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {t('admin.staff.columns.lastLogin')}
                                            :{' '}
                                            {member.last_login_at
                                                ? formatDateTime(
                                                      member.last_login_at,
                                                      displayTimezone,
                                                  )
                                                : t('admin.staff.never')}
                                        </p>
                                    </div>
                                    {isSelf ? (
                                        <p className="text-xs text-muted-foreground">
                                            {t('admin.staff.selfNotice')}
                                        </p>
                                    ) : (
                                        <div className="flex flex-wrap gap-2">
                                            {member.is_active &&
                                                !member.is_activated && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.visit(
                                                                staffRoutes.activationLink.store(
                                                                    member.id,
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <KeyRound aria-hidden="true" />
                                                        {t(
                                                            'admin.staff.activationLink',
                                                        )}
                                                    </Button>
                                                )}
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setEditing(member)
                                                }
                                            >
                                                <Pencil aria-hidden="true" />
                                                {t('common.edit')}
                                                <span className="sr-only">
                                                    : {member.name}
                                                </span>
                                            </Button>
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <CreateDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                jobRoles={jobRoles}
            />
            {editing && (
                <EditDialog
                    key={editing.id}
                    member={editing}
                    jobRoles={jobRoles}
                    onClose={() => setEditing(null)}
                />
            )}
            {link && (
                <ActivationLinkDialog
                    link={link}
                    onClose={() => setLink(null)}
                    description={t('admin.staff.activationDescription')}
                />
            )}
        </>
    );
}

StaffIndex.layout = {
    breadcrumbs: [{ title: t('admin.staff.title'), href: staffRoutes.index() }],
};
