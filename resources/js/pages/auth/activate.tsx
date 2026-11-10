import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { store } from '@/routes/activation';

type Props = {
    userId: string;
    token: string;
    name: string;
    email: string;
    passwordRules: string;
};

export default function Activate({
    userId,
    token,
    name,
    email,
    passwordRules,
}: Props) {
    const form = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(store({ user: userId, token }), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title={t('auth.activate.title')} />

            <p className="text-center text-sm text-muted-foreground">
                {t('auth.activate.description', { name })}
            </p>

            <form onSubmit={submit} className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">{t('common.email')}</Label>
                    <Input id="email" type="email" value={email} readOnly />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">{t('common.newPassword')}</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        autoComplete="new-password"
                        autoFocus
                        required
                        value={form.data.password}
                        onChange={(event) =>
                            form.setData('password', event.target.value)
                        }
                        passwordrules={passwordRules}
                    />
                    <InputError message={form.errors.password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">
                        {t('common.confirmPassword')}
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autoComplete="new-password"
                        required
                        value={form.data.password_confirmation}
                        onChange={(event) =>
                            form.setData(
                                'password_confirmation',
                                event.target.value,
                            )
                        }
                        passwordrules={passwordRules}
                    />
                    <InputError message={form.errors.password_confirmation} />
                </div>

                <Button
                    type="submit"
                    className="w-full"
                    disabled={form.processing}
                >
                    {form.processing && <Spinner />}
                    {t('auth.activate.submit')}
                </Button>
            </form>
        </>
    );
}

Activate.layout = {
    title: t('auth.activate.title'),
    description: '',
};
