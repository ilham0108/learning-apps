import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t, tList } from '@/lib/i18n';
import { store } from '@/routes/consent';

type Props = {
    policyVersion: string;
    previouslyAccepted: boolean;
};

export default function Consent({ policyVersion, previouslyAccepted }: Props) {
    const form = useForm({ accepted: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(store());
    };

    return (
        <>
            <Head title={t('auth.consent.title')} />

            <form onSubmit={submit} className="grid gap-6">
                {previouslyAccepted && (
                    <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
                        {t('auth.consent.updated')}
                    </p>
                )}

                <ul className="list-disc space-y-2 pl-5 text-sm text-muted-foreground">
                    {tList('auth.consent.points').map((point) => (
                        <li key={point}>{point}</li>
                    ))}
                </ul>

                <p className="text-xs text-muted-foreground">
                    {t('auth.consent.version', { version: policyVersion })}
                </p>

                <div className="grid gap-2">
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="accepted"
                            checked={form.data.accepted}
                            onCheckedChange={(checked) =>
                                form.setData('accepted', checked === true)
                            }
                            aria-invalid={form.errors.accepted !== undefined}
                        />
                        <Label
                            htmlFor="accepted"
                            className="leading-snug font-normal"
                        >
                            {t('auth.consent.accept')}
                        </Label>
                    </div>
                    <InputError message={form.errors.accepted} />
                </div>

                <Button
                    type="submit"
                    className="w-full"
                    disabled={form.processing || !form.data.accepted}
                >
                    {form.processing && <Spinner />}
                    {t('auth.consent.submit')}
                </Button>
            </form>
        </>
    );
}

Consent.layout = {
    title: t('auth.consent.title'),
    description: t('auth.consent.description'),
};
