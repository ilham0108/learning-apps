import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/**
 * Daftar centang untuk memilih beberapa item (misalnya program prasyarat).
 */
export function MultiCheckbox({
    idPrefix,
    legend,
    options,
    value,
    onChange,
    emptyText,
}: {
    idPrefix: string;
    legend: string;
    options: { id: string; name: string }[];
    value: string[];
    onChange: (value: string[]) => void;
    emptyText: string;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="sr-only">{legend}</legend>
            {options.length === 0 ? (
                <p className="text-sm text-muted-foreground">{emptyText}</p>
            ) : (
                <div className="grid max-h-56 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                    {options.map((option) => (
                        <div
                            key={option.id}
                            className="flex items-center gap-2"
                        >
                            <Checkbox
                                id={`${idPrefix}-${option.id}`}
                                checked={value.includes(option.id)}
                                onCheckedChange={(checked) =>
                                    onChange(
                                        checked === true
                                            ? [...value, option.id]
                                            : value.filter(
                                                  (id) => id !== option.id,
                                              ),
                                    )
                                }
                            />
                            <Label
                                htmlFor={`${idPrefix}-${option.id}`}
                                className="font-normal"
                            >
                                {option.name}
                            </Label>
                        </div>
                    ))}
                </div>
            )}
        </fieldset>
    );
}
