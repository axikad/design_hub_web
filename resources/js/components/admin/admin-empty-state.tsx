import { Inbox, type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

type Props = {
    title: string;
    description: string;
    icon?: LucideIcon;
    action?: ReactNode;
};

export function AdminEmptyState({ title, description, icon: Icon = Inbox, action }: Props) {
    return (
        <div className="flex min-h-72 flex-col items-center justify-center px-6 py-12 text-center">
            <div className="flex size-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                <Icon className="size-6" />
            </div>
            <h3 className="mt-5 text-base font-bold text-slate-900 dark:text-white">{title}</h3>
            <p className="mt-2 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">{description}</p>
            {action ? <div className="mt-5 [&_[data-slot=button]]:rounded-xl">{action}</div> : null}
        </div>
    );
}
