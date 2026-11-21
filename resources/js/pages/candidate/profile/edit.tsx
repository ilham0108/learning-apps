import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import {
    CompletenessCard,
    sectionIds,
} from '@/components/candidate-profile/completeness-card';
import { DocumentsSection } from '@/components/candidate-profile/documents-section';
import { EducationSection } from '@/components/candidate-profile/education-section';
import { ExperienceSection } from '@/components/candidate-profile/experience-section';
import { PersonalForm } from '@/components/candidate-profile/personal-form';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { t } from '@/lib/i18n';
import candidateProfile from '@/routes/candidate-profile';
import type { PersonalData } from '@/types';

function Section({
    id,
    title,
    description,
    required,
    children,
}: {
    id: string;
    title: string;
    description?: string;
    required: boolean;
    children: ReactNode;
}) {
    return (
        // scroll-mt menjaga judul bagian tidak tertutup header saat dibuka dari daftar periksa.
        <Card id={id} className="scroll-mt-20">
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                    {title}
                    <StatusBadge
                        label={
                            required
                                ? t('candidate.profile.required')
                                : t('candidate.profile.optional')
                        }
                        tone={required ? 'info' : 'neutral'}
                    />
                </CardTitle>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export default function CandidateProfileEdit({
    personal,
    educations,
    experiences,
    documents,
    completeness,
}: PersonalData) {
    return (
        <>
            <Head title={t('candidate.profile.title')} />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('candidate.profile.title')}
                    description={t('candidate.profile.description')}
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    <aside className="lg:order-last">
                        <div className="lg:sticky lg:top-6">
                            <CompletenessCard completeness={completeness} />
                        </div>
                    </aside>

                    <div className="min-w-0 space-y-6">
                        <Section
                            id={sectionIds.personal}
                            title={t('candidate.profile.personal.title')}
                            required
                        >
                            <PersonalForm personal={personal} />
                        </Section>

                        <Section
                            id={sectionIds.education}
                            title={t('candidate.profile.education.title')}
                            description={t(
                                'candidate.profile.education.description',
                            )}
                            required
                        >
                            <EducationSection educations={educations} />
                        </Section>

                        <Section
                            id="pengalaman"
                            title={t('candidate.profile.experience.title')}
                            description={t(
                                'candidate.profile.experience.description',
                            )}
                            required={false}
                        >
                            <ExperienceSection experiences={experiences} />
                        </Section>

                        <Section
                            id={sectionIds.cv}
                            title={t('candidate.profile.documents.title')}
                            description={t(
                                'candidate.profile.documents.description',
                            )}
                            required
                        >
                            <DocumentsSection documents={documents} />
                        </Section>
                    </div>
                </div>
            </div>
        </>
    );
}

CandidateProfileEdit.layout = {
    breadcrumbs: [
        { title: t('candidate.profile.title'), href: candidateProfile.edit() },
    ],
};
