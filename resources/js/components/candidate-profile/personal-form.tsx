import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/app/form-field';
import { OptionSelect } from '@/components/app/option-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import candidateProfile from '@/routes/candidate-profile';
import type { MaritalStatus, PersonalData, Religion } from '@/types';

const religions: Religion[] = [
    'islam',
    'kristen',
    'katolik',
    'hindu',
    'buddha',
    'konghucu',
    'kepercayaan',
    'tidak_disebutkan',
];
const maritalStatuses: MaritalStatus[] = [
    'belum_menikah',
    'menikah',
    'cerai',
    'tidak_disebutkan',
];

export function PersonalForm({
    personal,
}: {
    personal: PersonalData['personal'];
}) {
    const form = useForm({
        name: personal.name,
        phone: personal.phone ?? '',
        city: personal.city ?? '',
        birth_date: personal.birth_date ?? '',
        religion: personal.religion ?? '',
        marital_status: personal.marital_status ?? '',
        linkedin_url: personal.linkedin_url ?? '',
        portfolio_url: personal.portfolio_url ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(candidateProfile.personal.update(), {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="name"
                    label={t('candidate.profile.personal.name')}
                    error={form.errors.name}
                >
                    <Input
                        id="name"
                        required
                        maxLength={255}
                        autoComplete="name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="email"
                    label={t('candidate.profile.personal.email')}
                    description={t('candidate.profile.personal.emailHelp')}
                >
                    <Input
                        id="email"
                        value={personal.email}
                        readOnly
                        disabled
                        aria-describedby="email-description"
                    />
                </FormField>
                <FormField
                    id="phone"
                    label={t('candidate.profile.personal.phone')}
                    error={form.errors.phone}
                >
                    <Input
                        id="phone"
                        type="tel"
                        required
                        inputMode="tel"
                        autoComplete="tel"
                        placeholder="08xxxxxxxxxx"
                        value={form.data.phone}
                        onChange={(event) =>
                            form.setData('phone', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="city"
                    label={t('candidate.profile.personal.city')}
                    error={form.errors.city}
                >
                    <Input
                        id="city"
                        required
                        maxLength={100}
                        autoComplete="address-level2"
                        placeholder={t(
                            'candidate.profile.personal.cityPlaceholder',
                        )}
                        value={form.data.city}
                        onChange={(event) =>
                            form.setData('city', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="birth_date"
                    label={t('candidate.profile.personal.birthDate')}
                    error={form.errors.birth_date}
                >
                    <Input
                        id="birth_date"
                        type="date"
                        required
                        autoComplete="bday"
                        value={form.data.birth_date}
                        onChange={(event) =>
                            form.setData('birth_date', event.target.value)
                        }
                    />
                </FormField>
            </div>

            <div className="space-y-3">
                <p
                    id="sensitive-note"
                    className="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground"
                >
                    {t('candidate.profile.personal.sensitiveNote')}
                </p>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="religion"
                        label={t('candidate.profile.personal.religion')}
                        error={form.errors.religion}
                    >
                        <OptionSelect
                            id="religion"
                            value={form.data.religion || null}
                            onChange={(value) =>
                                form.setData('religion', value ?? '')
                            }
                            options={religions.map((religion) => ({
                                value: religion,
                                label: t(`status.religion.${religion}`),
                            }))}
                            placeholder={t('candidate.profile.select')}
                            invalid={form.errors.religion !== undefined}
                        />
                    </FormField>
                    <FormField
                        id="marital_status"
                        label={t('candidate.profile.personal.maritalStatus')}
                        error={form.errors.marital_status}
                    >
                        <OptionSelect
                            id="marital_status"
                            value={form.data.marital_status || null}
                            onChange={(value) =>
                                form.setData('marital_status', value ?? '')
                            }
                            options={maritalStatuses.map((status) => ({
                                value: status,
                                label: t(`status.maritalStatus.${status}`),
                            }))}
                            placeholder={t('candidate.profile.select')}
                            invalid={form.errors.marital_status !== undefined}
                        />
                    </FormField>
                </div>
            </div>

            <fieldset className="space-y-4">
                <legend className="text-sm font-medium">
                    {t('candidate.profile.personal.linksTitle')}{' '}
                    <span className="font-normal text-muted-foreground">
                        ({t('common.optional')})
                    </span>
                </legend>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="linkedin_url"
                        label={t('candidate.profile.personal.linkedin')}
                        error={form.errors.linkedin_url}
                    >
                        <Input
                            id="linkedin_url"
                            type="url"
                            maxLength={255}
                            placeholder={t(
                                'candidate.profile.personal.linkedinPlaceholder',
                            )}
                            value={form.data.linkedin_url}
                            onChange={(event) =>
                                form.setData('linkedin_url', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="portfolio_url"
                        label={t('candidate.profile.personal.portfolio')}
                        error={form.errors.portfolio_url}
                    >
                        <Input
                            id="portfolio_url"
                            type="url"
                            maxLength={255}
                            placeholder={t(
                                'candidate.profile.personal.portfolioPlaceholder',
                            )}
                            value={form.data.portfolio_url}
                            onChange={(event) =>
                                form.setData(
                                    'portfolio_url',
                                    event.target.value,
                                )
                            }
                        />
                    </FormField>
                </div>
            </fieldset>

            <Button type="submit" disabled={form.processing}>
                {form.processing && <Spinner />}
                {t('candidate.profile.personal.save')}
            </Button>
        </form>
    );
}
