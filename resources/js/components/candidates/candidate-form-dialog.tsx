import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/app/form-field';
import { OptionSelect } from '@/components/app/option-select';
import { CandidateFields } from '@/components/candidates/candidate-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import candidates from '@/routes/admin/candidates';
import type { CampaignOption, CandidateDetail } from '@/types';

type Props =
    | {
          mode: 'create';
          campaignOptions: CampaignOption[];
          /** Gelombang yang sudah dipilih, misalnya saat dibuka dari halaman gelombang. */
          selectedCampaign?: string;
          onClose: () => void;
      }
    | {
          mode: 'edit';
          candidate: CandidateDetail;
          onClose: () => void;
      };

/**
 * Modal tambah kandidat ke gelombang atau ubah data kandidat. Setelah ditambahkan,
 * server mengarahkan ke halaman detail kandidat.
 */
export function CandidateFormDialog(props: Props) {
    const candidate = props.mode === 'edit' ? props.candidate : null;
    const form = useForm({
        name: candidate?.name ?? '',
        email: candidate?.email ?? '',
        phone: candidate?.phone ?? '',
        last_education: candidate?.last_education ?? '',
        campaign: props.mode === 'create' ? (props.selectedCampaign ?? '') : '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (props.mode === 'edit') {
            form.transform(({ campaign: _campaign, ...data }) => data);
        }

        form.submit(
            candidate ? candidates.update(candidate.id) : candidates.store(),
            {
                preserveScroll: true,
                onSuccess: props.onClose,
            },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && props.onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit} className="grid gap-6">
                    <DialogHeader>
                        <DialogTitle>
                            {candidate
                                ? t('admin.candidates.editTitle')
                                : t('admin.candidates.createTitle')}
                        </DialogTitle>
                        <DialogDescription>
                            {candidate
                                ? candidate.candidate_number
                                : t('admin.candidates.createDescription')}
                        </DialogDescription>
                    </DialogHeader>

                    {props.mode === 'create' && (
                        <FormField
                            id="candidate-campaign"
                            label={t('admin.candidates.fields.campaign')}
                            error={form.errors.campaign}
                        >
                            <OptionSelect
                                id="candidate-campaign"
                                value={form.data.campaign || null}
                                onChange={(value) =>
                                    form.setData('campaign', value ?? '')
                                }
                                options={props.campaignOptions.map(
                                    (campaign) => ({
                                        value: campaign.id,
                                        label: `${campaign.name} (${t(`status.campaign.${campaign.status}`)})`,
                                    }),
                                )}
                                placeholder={t(
                                    'admin.candidates.fields.campaignPlaceholder',
                                )}
                                invalid={form.errors.campaign !== undefined}
                            />
                        </FormField>
                    )}

                    <CandidateFields
                        data={form.data}
                        errors={form.errors}
                        onChange={(key, value) => form.setData(key, value)}
                    />

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={props.onClose}
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
