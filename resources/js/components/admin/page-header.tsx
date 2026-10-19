import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { type ReactNode } from 'react';

type Props = {
    title: string;
    description?: string;
    createHref?: string;
    createLabel?: string;
    actions?: ReactNode;
};

export function AdminPageHeader({ title, description, createHref, createLabel = 'Add new', actions }: Props) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p className="mb-1 text-[10px] font-bold tracking-[0.16em] text-indigo-600 uppercase dark:text-indigo-400">Administration</p>
                <h1 className="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl dark:text-white">{title}</h1>
                {description ? <p className="mt-1.5 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">{description}</p> : null}
            </div>
            <div className="flex shrink-0 items-center gap-2">
                {actions}
                {createHref ? (
                    <Button asChild className="h-10 rounded-xl bg-indigo-600 px-4 font-semibold shadow-md shadow-indigo-500/20 hover:bg-indigo-700">
                        <Link href={createHref}>
                            <Plus className="h-4 w-4" />
                            {createLabel}
                        </Link>
                    </Button>
                ) : null}
            </div>
        </div>
    );
}
