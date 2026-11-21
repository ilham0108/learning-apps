import { usePage } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import type { ReactNode } from 'react';
import { educationPeriod } from '@/components/candidate-profile/education-section';
import { experiencePeriod } from '@/components/candidate-profile/experience-section';
import { ProgressBar } from '@/components/app/progress-bar';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { MaterialIcon } from '@/lib/icons';
import type { PersonalData } from '@/types';

/** Usia dalam tahun penuh pada hari ini. */
function ageFrom(birthDate: string): number {
    const birth = new Date(`${birthDate}T00:00:00`);
    const today = new Date();
    const age = today.getFullYear() - birth.getFullYear();
    const hadBirthday =
        today.getMonth() > birth.getMonth() ||
        (today.getMonth() === birth.getMonth() &&
            today.getDate() >= birth.getDate());

    return hadBirthday ? age : age - 1;
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium break-words">{children}</dd>
        </div>
    );
}

/**
 * Data diri yang diisi kandidat, untuk HR. Dokumen dibuka lewat URL bertanda
 * tangan; pembukaan foto KTP dicatat di audit log oleh server.
 */
export function PersonalDataCard({ data }: { data: PersonalData }) {
    const { displayTimezone } = usePage().props;
    const { personal, educations, experiences, documents, completeness } = data;
    const none = t('admin.personalData.none');
    const photo = documents.find((document) => document.type === 'photo');
    const missing = completeness.items
        .filter((item) => !item.done)
        .map((item) => t(`candidate.profile.completeness.items.${item.key}`));

    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle className="flex items-center gap-3">
                    {photo && (
                        <img
                            src={photo.url}
                            alt=""
                            className="size-10 rounded-full border object-cover"
                        />
                    )}
                    {t('admin.personalData.title')}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-5 text-sm">
                <div className="space-y-2">
                    <ProgressBar
                        percent={completeness.percent}
                        label={t('admin.personalData.completeness', {
                            percent: completeness.percent,
                        })}
                    />
                    {missing.length > 0 && (
                        <p className="text-xs text-amber-800 dark:text-amber-300">
                            {t('admin.personalData.missing', {
                                items: missing.join(', '),
                            })}
                        </p>
                    )}
                </div>

                <dl className="grid gap-3">
                    <Row label={t('candidate.profile.personal.city')}>
                        {personal.city ?? none}
                    </Row>
                    <Row label={t('admin.personalData.birthDate')}>
                        {personal.birth_date
                            ? `${formatDate(personal.birth_date, displayTimezone)} (${t(
                                  'admin.personalData.age',
                                  { age: ageFrom(personal.birth_date) },
                              )})`
                            : none}
                    </Row>
                    <Row label={t('candidate.profile.personal.religion')}>
                        {personal.religion
                            ? t(`status.religion.${personal.religion}`)
                            : none}
                    </Row>
                    <Row label={t('candidate.profile.personal.maritalStatus')}>
                        {personal.marital_status
                            ? t(
                                  `status.maritalStatus.${personal.marital_status}`,
                              )
                            : none}
                    </Row>
                    {(personal.linkedin_url || personal.portfolio_url) && (
                        <Row label={t('candidate.profile.personal.linksTitle')}>
                            <span className="flex flex-col gap-1">
                                {[personal.linkedin_url, personal.portfolio_url]
                                    .filter(
                                        (url): url is string => url !== null,
                                    )
                                    .map((url) => (
                                        <a
                                            key={url}
                                            href={url}
                                            target="_blank"
                                            rel="noopener noreferrer nofollow"
                                            className="inline-flex items-center gap-1 truncate text-primary hover:underline"
                                        >
                                            <ExternalLink
                                                className="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            <span className="truncate">
                                                {url}
                                            </span>
                                        </a>
                                    ))}
                            </span>
                        </Row>
                    )}
                </dl>

                <p className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                    {t('admin.personalData.sensitiveNote')}
                </p>

                <section className="space-y-2">
                    <h3 className="font-medium">
                        {t('admin.personalData.educations')}
                    </h3>
                    {educations.length === 0 ? (
                        <p className="text-muted-foreground">
                            {t('admin.personalData.noEntries')}
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {educations.map((education) => (
                                <li key={education.id}>
                                    <p className="font-medium">
                                        {t(
                                            `status.educationLevel.${education.level}`,
                                        )}{' '}
                                        · {education.institution}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {education.major &&
                                            `${education.major} · `}
                                        {educationPeriod(education)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="space-y-2">
                    <h3 className="font-medium">
                        {t('admin.personalData.experiences')}
                    </h3>
                    {experiences.length === 0 ? (
                        <p className="text-muted-foreground">
                            {t('admin.personalData.noEntries')}
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {experiences.map((experience) => (
                                <li key={experience.id}>
                                    <p className="font-medium">
                                        {experience.job_title} ·{' '}
                                        {experience.company}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {experiencePeriod(
                                            experience,
                                            displayTimezone,
                                        )}
                                    </p>
                                    {experience.description && (
                                        <p className="mt-1 whitespace-pre-line">
                                            {experience.description}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="space-y-2">
                    <h3 className="font-medium">
                        {t('admin.personalData.documents')}
                    </h3>
                    {documents.length === 0 ? (
                        <p className="text-muted-foreground">
                            {t('admin.personalData.noEntries')}
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {documents.map((document) => (
                                <li key={document.id}>
                                    <a
                                        href={document.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-2 p-2.5 hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <MaterialIcon
                                            className="size-4 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-medium">
                                                {document.label ??
                                                    t(
                                                        `candidate.profile.documents.types.${document.type}.label`,
                                                    )}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {document.name}
                                            </span>
                                        </span>
                                        <ExternalLink
                                            className="size-4 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </CardContent>
        </Card>
    );
}
