import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

type Props = {
    children: ReactNode;
    footer?: ReactNode;
    className?: string;
};

export function AdminTablePanel({ children, footer, className }: Props) {
    return (
        <section
            className={cn(
                'overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900',
                className,
            )}
        >
            <div className="overflow-x-auto">{children}</div>
            {footer ? <div className="border-t border-slate-200 dark:border-slate-800">{footer}</div> : null}
        </section>
    );
}
