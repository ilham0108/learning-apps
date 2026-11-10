import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { edit } from '@/routes/profile';

type Props = {
    canChangeEmail: boolean;
};

export default function Profile({ canChangeEmail }: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={t('settings.profile.pageTitle')} />

            <h1 className="sr-only">{t('settings.profile.pageTitle')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('settings.profile.title')}
                    description={
                        canChangeEmail
                            ? t('settings.profile.description')
                            : t('settings.profile.candidateDescription')
                    }
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('common.fullName')}
                                </Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder={t('common.fullName')}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('common.emailAddress')}
                                </Label>

                                {canChangeEmail ? (
                                    <Input
                                        id="email"
                                        type="email"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder={t('common.emailAddress')}
                                    />
                                ) : (
                                    <>
                                        <Input
                                            id="email"
                                            type="email"
                                            className="mt-1 block w-full"
                                            value={auth.user.email}
                                            readOnly
                                            aria-describedby="email-help"
                                        />
                                        <p
                                            id="email-help"
                                            className="text-sm text-muted-foreground"
                                        >
                                            {t(
                                                'settings.profile.emailManagedByHr',
                                            )}
                                        </p>
                                    </>
                                )}

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    {t('common.save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: t('settings.profile.pageTitle'),
            href: edit(),
        },
    ],
};
