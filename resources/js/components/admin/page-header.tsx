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
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 className="text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{title}</h1>
                {description ? <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">{description}</p> : null}
            </div>
            <div className="flex shrink-0 items-center gap-2">
                {actions}
                {createHref ? (
                    <Button asChild className="bg-indigo-600 hover:bg-indigo-700">
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
