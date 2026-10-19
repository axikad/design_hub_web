import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Search, X } from 'lucide-react';
import { type ComponentProps } from 'react';

type Props = ComponentProps<typeof Input> & {
    onClear?: () => void;
};

export function AdminSearchInput({ className, value, onClear, ...props }: Props) {
    const hasValue = typeof value === 'string' && value.length > 0;

    return (
        <div className="relative min-w-0 flex-1 sm:max-w-sm">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
            <Input
                type="search"
                value={value}
                className={cn(
                    'h-10 rounded-xl border-slate-200 bg-slate-50 pr-9 pl-9 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950',
                    className,
                )}
                {...props}
            />
            {hasValue && onClear ? (
                <button
                    type="button"
                    onClick={onClear}
                    className="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    aria-label="Clear search"
                >
                    <X className="size-3.5" />
                </button>
            ) : null}
        </div>
    );
}
