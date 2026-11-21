import { ProgressBar } from '@/components/app/progress-bar';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { t } from '@/lib/i18n';
import { CompletedIcon, NotStartedIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';
import type { ProfileCompleteness, ProfileItem } from '@/types';

/** Bagian halaman yang dituju saat item kelengkapan diklik. */
export const sectionIds: Record<ProfileItem, string> = {
    personal: 'data-pribadi',
    education: 'pendidikan',
    cv: 'dokumen',
    id_card: 'dokumen',
    photo: 'dokumen',
};

/**
 * Meteran kelengkapan dan daftar periksa. Setiap item adalah tautan ke bagian
 * yang perlu diisi, dan statusnya dibaca dari teks, tidak hanya ikon.
 */
export function CompletenessCard({
    completeness,
}: {
    completeness: ProfileCompleteness;
}) {
    return (
        <Card
            className={cn(
                completeness.complete &&
                    'border-green-300 dark:border-green-900',
            )}
        >
            <CardHeader>
                <CardTitle className="text-base">
                    {t('candidate.profile.completeness.title')}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <ProgressBar
                    percent={completeness.percent}
                    label={t('candidate.profile.completeness.value', {
                        percent: completeness.percent,
                    })}
                />
                {completeness.complete ? (
                    <p className="text-sm text-green-700 dark:text-green-400">
                        {t('candidate.profile.completeness.complete')}
                    </p>
                ) : (
                    <ul className="space-y-1">
                        {completeness.items.map((item) => {
                            const Icon = item.done
                                ? CompletedIcon
                                : NotStartedIcon;

                            return (
                                <li key={item.key}>
                                    <a
                                        href={`#${sectionIds[item.key]}`}
                                        className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <Icon
                                            className={cn(
                                                'size-4 shrink-0',
                                                item.done
                                                    ? 'text-green-600'
                                                    : 'text-muted-foreground',
                                            )}
                                            aria-hidden="true"
                                        />
                                        <span
                                            className={cn(
                                                'flex-1',
                                                item.done &&
                                                    'text-muted-foreground line-through',
                                            )}
                                        >
                                            {t(
                                                `candidate.profile.completeness.items.${item.key}`,
                                            )}
                                        </span>
                                        <span className="sr-only">
                                            ,{' '}
                                            {item.done
                                                ? t(
                                                      'candidate.profile.completeness.done',
                                                  )
                                                : t(
                                                      'candidate.profile.completeness.missing',
                                                  )}
                                        </span>
                                    </a>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
