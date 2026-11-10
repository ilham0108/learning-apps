import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';

/**
 * Kolom pencarian yang mengirim kata kunci setelah pengguna berhenti mengetik.
 */
export function SearchInput({
    value,
    onSearch,
    placeholder,
    label,
}: {
    value: string | null;
    onSearch: (value: string | null) => void;
    placeholder: string;
    label: string;
}) {
    const [term, setTerm] = useState(value ?? '');

    useEffect(() => {
        const trimmed = term.trim();

        if (trimmed === (value ?? '')) {
            return;
        }

        const timer = window.setTimeout(
            () => onSearch(trimmed === '' ? null : trimmed),
            400,
        );

        return () => window.clearTimeout(timer);
    }, [term, value, onSearch]);

    return (
        <div className="relative w-full sm:max-w-sm">
            <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                type="search"
                value={term}
                onChange={(event) => setTerm(event.target.value)}
                placeholder={placeholder}
                aria-label={label}
                className="pl-9"
            />
        </div>
    );
}
