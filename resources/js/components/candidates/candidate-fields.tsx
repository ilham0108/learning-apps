import { FormField } from '@/components/app/form-field';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';

export type CandidateFormData = {
    name: string;
    email: string;
    phone: string;
    last_education: string;
};

/**
 * Field data kandidat yang dipakai bersama form tambah dan ubah.
 * Hanya data yang dibutuhkan untuk seleksi.
 */
export function CandidateFields({
    data,
    errors,
    onChange: set,
}: {
    data: CandidateFormData;
    errors: Partial<Record<keyof CandidateFormData, string>>;
    onChange: (key: keyof CandidateFormData, value: string) => void;
}) {
    return (
        <>
            <FormField
                id="name"
                label={t('admin.candidates.fields.name')}
                error={errors.name}
            >
                <Input
                    id="name"
                    value={data.name}
                    onChange={(event) => set('name', event.target.value)}
                    autoComplete="off"
                    required
                />
            </FormField>

            <FormField
                id="email"
                label={t('admin.candidates.fields.email')}
                error={errors.email}
            >
                <Input
                    id="email"
                    type="email"
                    value={data.email}
                    onChange={(event) => set('email', event.target.value)}
                    autoComplete="off"
                    required
                />
            </FormField>

            <div className="grid gap-6 sm:grid-cols-2">
                <FormField
                    id="phone"
                    label={t('admin.candidates.fields.phone')}
                    error={errors.phone}
                    optional
                >
                    <Input
                        id="phone"
                        type="tel"
                        inputMode="tel"
                        value={data.phone}
                        onChange={(event) => set('phone', event.target.value)}
                        placeholder={t(
                            'admin.candidates.fields.phonePlaceholder',
                        )}
                    />
                </FormField>
                <FormField
                    id="last_education"
                    label={t('admin.candidates.fields.lastEducation')}
                    error={errors.last_education}
                    optional
                >
                    <Input
                        id="last_education"
                        value={data.last_education}
                        onChange={(event) =>
                            set('last_education', event.target.value)
                        }
                        placeholder={t(
                            'admin.candidates.fields.lastEducationPlaceholder',
                        )}
                    />
                </FormField>
            </div>

            <p className="text-sm text-muted-foreground">
                {t('admin.candidates.privacyNote')}
            </p>
        </>
    );
}
