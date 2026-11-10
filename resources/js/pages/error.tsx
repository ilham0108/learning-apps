import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { homeFor } from '@/lib/navigation';
import { dashboard } from '@/routes';

type Props = {
    status: 403 | 404 | 429 | 500 | 503;
};

/**
 * Halaman error tanpa detail teknis dan tanpa menyebut data milik pengguna lain.
 */
export default function ErrorPage({ status }: Props) {
    const { auth } = usePage().props;
    const home = auth.user ? homeFor(auth.user.role) : dashboard();

    return (
        <>
            <Head title={t(`errors.${status}.title`)} />

            <main className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-center">
                <AppLogoIcon className="size-10 text-primary" />
                <div className="space-y-2">
                    <p className="text-sm font-medium text-muted-foreground">
                        {status}
                    </p>
                    <h1 className="text-2xl font-semibold">
                        {t(`errors.${status}.title`)}
                    </h1>
                    <p className="max-w-md text-muted-foreground">
                        {t(`errors.${status}.description`)}
                    </p>
                </div>
                <Button asChild>
                    <Link href={home}>{t('errors.backHome')}</Link>
                </Button>
            </main>
        </>
    );
}
