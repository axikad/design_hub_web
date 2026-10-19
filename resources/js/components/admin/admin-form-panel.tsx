import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

type Props = {
    title: string;
    description?: string;
    icon?: LucideIcon;
    children: ReactNode;
    className?: string;
};

export function AdminFormPanel({ title, description, icon: Icon, children, className }: Props) {
    return (
        <section
            className={cn(
                'overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900',
                className,
            )}
        >
            <div className="flex items-start gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                {Icon ? (
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                        <Icon className="size-4.5" />
                    </div>
                ) : null}
                <div>
                    <h2 className="text-sm font-bold text-slate-900 dark:text-white">{title}</h2>
                    {description ? <p className="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{description}</p> : null}
                </div>
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}
