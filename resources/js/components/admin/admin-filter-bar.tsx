import { cn } from '@/lib/utils';
import { SlidersHorizontal } from 'lucide-react';
import { type ReactNode } from 'react';

type Props = {
    children: ReactNode;
    actions?: ReactNode;
    className?: string;
};

export function AdminFilterBar({ children, actions, className }: Props) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center dark:border-slate-800 dark:bg-slate-900',
                className,
            )}
        >
            <div className="hidden size-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 sm:flex dark:bg-slate-800 dark:text-slate-400">
                <SlidersHorizontal className="size-4" />
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">{children}</div>
            {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
        </div>
    );
}
