import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const ALL = '__all__';

export type Option = {
    value: string;
    label: string;
};

type Props = {
    id: string;
    value: string | null;
    onChange: (value: string | null) => void;
    options: Option[];
    placeholder?: string;
    /** Tambahkan pilihan "Semua" yang menghasilkan null (untuk filter). */
    allowAll?: boolean;
    ariaLabel?: string;
    className?: string;
    invalid?: boolean;
};

/**
 * Pilihan berbasis Radix Select. Radix tidak mendukung nilai kosong,
 * sehingga "Semua" diwakili nilai khusus dan dikembalikan sebagai null.
 */
export function OptionSelect({
    id,
    value,
    onChange,
    options,
    placeholder,
    allowAll = false,
    ariaLabel,
    className,
    invalid = false,
}: Props) {
    return (
        <Select
            value={value ?? (allowAll ? ALL : undefined)}
            onValueChange={(next) => onChange(next === ALL ? null : next)}
        >
            <SelectTrigger
                id={id}
                aria-label={ariaLabel}
                aria-invalid={invalid}
                // Lebar penuh di form; filter mengatur lebarnya sendiri lewat className.
                className={cn('w-full', className)}
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {allowAll && (
                    <SelectItem value={ALL}>{t('common.all')}</SelectItem>
                )}
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
