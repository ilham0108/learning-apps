import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';

type Props = {
    id: string;
    label: string;
    error?: string;
    description?: string;
    optional?: boolean;
    children: ReactNode;
};

/**
 * Label, input, keterangan, dan pesan error dalam satu susunan yang konsisten.
 * Setiap input wajib memiliki label (aksesibilitas).
 */
export function FormField({
    id,
    label,
    error,
    description,
    optional = false,
    children,
}: Props) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {label}
                {optional && (
                    <span className="ml-1 font-normal text-muted-foreground">
                        ({t('common.optional')})
                    </span>
                )}
            </Label>
            {children}
            {description && (
                <p
                    id={`${id}-description`}
                    className="text-sm text-muted-foreground"
                >
                    {description}
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}
