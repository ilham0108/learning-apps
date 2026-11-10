import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/app/form-field';
import { OptionSelect } from '@/components/app/option-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import campaigns from '@/routes/admin/campaigns';
import type { CampaignDetail, CampaignStatus, PositionOption } from '@/types';

const statuses: CampaignStatus[] = ['draft', 'open', 'closed'];

/**
 * Modal buat/ubah gelombang. Setelah dibuat, server mengarahkan ke halaman detail gelombang.
 */
export function CampaignFormDialog({
    campaign,
    positions,
    onClose,
}: {
    campaign: CampaignDetail | null;
    positions: PositionOption[];
    onClose: () => void;
}) {
    const form = useForm({
        name: campaign?.name ?? '',
        position: campaign?.position.id ?? '',
        starts_on: campaign?.starts_on ?? '',
        ends_on: campaign?.ends_on ?? '',
        status: campaign?.status ?? ('draft' as CampaignStatus),
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(
            campaign ? campaigns.update(campaign.id) : campaigns.store(),
            {
                preserveScroll: true,
                onSuccess: onClose,
            },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit} className="grid gap-6">
                    <DialogHeader>
                        <DialogTitle>
                            {campaign
                                ? t('admin.campaigns.editTitle')
                                : t('admin.campaigns.createTitle')}
                        </DialogTitle>
                    </DialogHeader>

                    <FormField
                        id="campaign-name"
                        label={t('admin.campaigns.fields.name')}
                        error={form.errors.name}
                    >
                        <Input
                            id="campaign-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder={t(
                                'admin.campaigns.fields.namePlaceholder',
                            )}
                            required
                            autoFocus
                        />
                    </FormField>

                    <FormField
                        id="campaign-position"
                        label={t('admin.campaigns.fields.position')}
                        error={form.errors.position}
                    >
                        <OptionSelect
                            id="campaign-position"
                            value={form.data.position || null}
                            onChange={(value) =>
                                form.setData('position', value ?? '')
                            }
                            options={positions.map((position) => ({
                                value: position.id,
                                label: `${position.name} — ${position.department}`,
                            }))}
                            placeholder={t(
                                'admin.campaigns.fields.positionPlaceholder',
                            )}
                            invalid={form.errors.position !== undefined}
                        />
                    </FormField>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            id="campaign-starts"
                            label={t('admin.campaigns.fields.startsOn')}
                            error={form.errors.starts_on}
                        >
                            <Input
                                id="campaign-starts"
                                type="date"
                                value={form.data.starts_on}
                                onChange={(event) =>
                                    form.setData(
                                        'starts_on',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            id="campaign-ends"
                            label={t('admin.campaigns.fields.endsOn')}
                            error={form.errors.ends_on}
                            optional
                        >
                            <Input
                                id="campaign-ends"
                                type="date"
                                value={form.data.ends_on}
                                min={form.data.starts_on || undefined}
                                onChange={(event) =>
                                    form.setData('ends_on', event.target.value)
                                }
                            />
                        </FormField>
                    </div>

                    <FormField
                        id="campaign-status"
                        label={t('admin.campaigns.fields.status')}
                        error={form.errors.status}
                        description={t('admin.campaigns.statusHelp')}
                    >
                        <OptionSelect
                            id="campaign-status"
                            value={form.data.status}
                            onChange={(value) =>
                                form.setData(
                                    'status',
                                    (value ?? 'draft') as CampaignStatus,
                                )
                            }
                            options={statuses.map((status) => ({
                                value: status,
                                label: t(`status.campaign.${status}`),
                            }))}
                        />
                    </FormField>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('common.cancel')}
                        </Button>
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
