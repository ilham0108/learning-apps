import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { EntryList } from '@/components/candidate-profile/entry-list';
import { FormField } from '@/components/app/form-field';
import { OptionSelect } from '@/components/app/option-select';
import { Button } from '@/components/ui/button';
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
import { t } from '@/lib/i18n';
import candidateProfile from '@/routes/candidate-profile';
import type { CandidateEducation, EducationLevel } from '@/types';

const levels: EducationLevel[] = [
    'sma_smk',
    'd1',
    'd2',
    'd3',
    'd4',
    's1',
    's2',
    's3',
];

export function educationPeriod(education: CandidateEducation): string {
    const end =
        education.graduation_year ?? t('candidate.profile.education.ongoing');

    return education.start_year
        ? `${education.start_year} – ${end}`
        : String(end);
}

function EducationDialog({
    entry,
    open,
    onOpenChange,
}: {
    entry: CandidateEducation | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        level: entry?.level ?? '',
        institution: entry?.institution ?? '',
        major: entry?.major ?? '',
        start_year: entry?.start_year?.toString() ?? '',
        graduation_year: entry?.graduation_year?.toString() ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(
            entry
                ? candidateProfile.educations.update(entry.id)
                : candidateProfile.educations.store(),
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {entry
                            ? t('candidate.profile.education.editTitle')
                            : t('candidate.profile.education.addTitle')}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <FormField
                        id="education-level"
                        label={t('candidate.profile.education.level')}
                        error={form.errors.level}
                    >
                        <OptionSelect
                            id="education-level"
                            value={form.data.level || null}
                            onChange={(value) =>
                                form.setData('level', value ?? '')
                            }
                            options={levels.map((level) => ({
                                value: level,
                                label: t(`status.educationLevel.${level}`),
                            }))}
                            placeholder={t('candidate.profile.select')}
                            invalid={form.errors.level !== undefined}
                        />
                    </FormField>
                    <FormField
                        id="education-institution"
                        label={t('candidate.profile.education.institution')}
                        error={form.errors.institution}
                    >
                        <Input
                            id="education-institution"
                            required
                            maxLength={150}
                            placeholder={t(
                                'candidate.profile.education.institutionPlaceholder',
                            )}
                            value={form.data.institution}
                            onChange={(event) =>
                                form.setData('institution', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="education-major"
                        label={t('candidate.profile.education.major')}
                        error={form.errors.major}
                        optional
                    >
                        <Input
                            id="education-major"
                            maxLength={150}
                            placeholder={t(
                                'candidate.profile.education.majorPlaceholder',
                            )}
                            value={form.data.major}
                            onChange={(event) =>
                                form.setData('major', event.target.value)
                            }
                        />
                    </FormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="education-start"
                            label={t('candidate.profile.education.startYear')}
                            error={form.errors.start_year}
                            optional
                        >
                            <Input
                                id="education-start"
                                type="number"
                                inputMode="numeric"
                                min={1950}
                                value={form.data.start_year}
                                onChange={(event) =>
                                    form.setData(
                                        'start_year',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="education-graduation"
                            label={t(
                                'candidate.profile.education.graduationYear',
                            )}
                            description={t(
                                'candidate.profile.education.graduationHelp',
                            )}
                            error={form.errors.graduation_year}
                        >
                            <Input
                                id="education-graduation"
                                type="number"
                                inputMode="numeric"
                                min={1950}
                                aria-describedby="education-graduation-description"
                                value={form.data.graduation_year}
                                onChange={(event) =>
                                    form.setData(
                                        'graduation_year',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </div>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                            >
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

export function EducationSection({
    educations,
}: {
    educations: CandidateEducation[];
}) {
    return (
        <EntryList
            entries={educations}
            empty={t('candidate.profile.education.empty')}
            addLabel={t('candidate.profile.education.add')}
            deleteTitle={t('candidate.profile.education.deleteTitle')}
            title={(education) => education.institution}
            destroy={(education) =>
                candidateProfile.educations.destroy(education.id)
            }
            render={(education) => (
                <>
                    <p className="font-medium">{education.institution}</p>
                    <p className="text-sm text-muted-foreground">
                        {t(`status.educationLevel.${education.level}`)}
                        {education.major && ` · ${education.major}`}
                    </p>
                    <p className="text-xs text-muted-foreground tabular-nums">
                        {educationPeriod(education)}
                    </p>
                </>
            )}
            form={({ entry, open, onOpenChange }) => (
                // Kunci baru setiap kali dibuka agar form selalu memuat data entri yang dipilih.
                <EducationDialog
                    key={`${entry?.id ?? 'new'}-${open}`}
                    entry={entry}
                    open={open}
                    onOpenChange={onOpenChange}
                />
            )}
        />
    );
}
