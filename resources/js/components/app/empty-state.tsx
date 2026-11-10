import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyIcon, ErrorIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    icon?: LucideIcon;
    action?: ReactNode;
    className?: string;
};

export function EmptyState({
    title,
    description,
    icon: Icon = EmptyIcon,
    action,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-12 text-center',
                className,
            )}
        >
            <Icon className="size-8 text-muted-foreground" aria-hidden="true" />
            <div className="space-y-1">
                <p className="font-medium">{title}</p>
                {description && (
                    <p className="max-w-md text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {action}
        </div>
    );
}

export function ErrorState(props: Omit<Props, 'icon'>) {
    return (
        <EmptyState
            {...props}
            icon={ErrorIcon}
            className={cn(
                'border-red-200 dark:border-red-900',
                props.className,
            )}
        />
    );
}
