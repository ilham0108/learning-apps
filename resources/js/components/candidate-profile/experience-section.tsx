import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { EntryList } from '@/components/candidate-profile/entry-list';
import { FormField } from '@/components/app/form-field';
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
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import candidateProfile from '@/routes/candidate-profile';
import type { CandidateExperience } from '@/types';

/** "Jan 2022 – sekarang" dari bulan berformat YYYY-MM. */
export function experiencePeriod(
    experience: CandidateExperience,
    timeZone: string,
): string {
    const month = (value: string) =>
        new Intl.DateTimeFormat('id-ID', {
            month: 'short',
            year: 'numeric',
            timeZone,
        }).format(new Date(`${value}-01T12:00:00Z`));

    return `${month(experience.started_on)} – ${
        experience.ended_on
            ? month(experience.ended_on)
            : t('candidate.profile.experience.current')
    }`;
}

function ExperienceDialog({
    entry,
    open,
    onOpenChange,
}: {
    entry: CandidateExperience | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        company: entry?.company ?? '',
        job_title: entry?.job_title ?? '',
        started_on: entry?.started_on ?? '',
        ended_on: entry?.ended_on ?? '',
        description: entry?.description ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(
            entry
                ? candidateProfile.experiences.update(entry.id)
                : candidateProfile.experiences.store(),
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
                            ? t('candidate.profile.experience.editTitle')
                            : t('candidate.profile.experience.addTitle')}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <FormField
                        id="experience-company"
                        label={t('candidate.profile.experience.company')}
                        error={form.errors.company}
                    >
                        <Input
                            id="experience-company"
                            required
                            maxLength={150}
                            value={form.data.company}
                            onChange={(event) =>
                                form.setData('company', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="experience-title"
                        label={t('candidate.profile.experience.jobTitle')}
                        error={form.errors.job_title}
                    >
                        <Input
                            id="experience-title"
                            required
                            maxLength={150}
                            value={form.data.job_title}
                            onChange={(event) =>
                                form.setData('job_title', event.target.value)
                            }
                        />
                    </FormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="experience-start"
                            label={t('candidate.profile.experience.startedOn')}
                            error={form.errors.started_on}
                        >
                            <Input
                                id="experience-start"
                                type="month"
                                required
                                value={form.data.started_on}
                                onChange={(event) =>
                                    form.setData(
                                        'started_on',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="experience-end"
                            label={t('candidate.profile.experience.endedOn')}
                            description={t(
                                'candidate.profile.experience.endedHelp',
                            )}
                            error={form.errors.ended_on}
                        >
                            <Input
                                id="experience-end"
                                type="month"
                                aria-describedby="experience-end-description"
                                value={form.data.ended_on}
                                onChange={(event) =>
                                    form.setData('ended_on', event.target.value)
                                }
                            />
                        </FormField>
                    </div>
                    <FormField
                        id="experience-description"
                        label={t(
                            'candidate.profile.experience.descriptionField',
                        )}
                        error={form.errors.description}
                        optional
                    >
                        <Textarea
                            id="experience-description"
                            rows={3}
                            maxLength={2000}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </FormField>
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

export function ExperienceSection({
    experiences,
}: {
    experiences: CandidateExperience[];
}) {
    const { displayTimezone } = usePage().props;

    return (
        <EntryList
            entries={experiences}
            empty={t('candidate.profile.experience.empty')}
            addLabel={t('candidate.profile.experience.add')}
            deleteTitle={t('candidate.profile.experience.deleteTitle')}
            title={(experience) =>
                `${experience.job_title}, ${experience.company}`
            }
            destroy={(experience) =>
                candidateProfile.experiences.destroy(experience.id)
            }
            render={(experience) => (
                <>
                    <p className="font-medium">{experience.job_title}</p>
                    <p className="text-sm text-muted-foreground">
                        {experience.company}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {experiencePeriod(experience, displayTimezone)}
                    </p>
                    {experience.description && (
                        <p className="mt-2 text-sm whitespace-pre-line">
                            {experience.description}
                        </p>
                    )}
                </>
            )}
            form={({ entry, open, onOpenChange }) => (
                <ExperienceDialog
                    key={`${entry?.id ?? 'new'}-${open}`}
                    entry={entry}
                    open={open}
                    onOpenChange={onOpenChange}
                />
            )}
        />
    );
}
