import { Link } from '@inertiajs/react';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

export function PaginationLinks<T>({ paginator }: { paginator: Paginated<T> }) {
    if (paginator.links.length <= 3) {
        return null;
    }

    return (
        <nav className="flex flex-wrap gap-1 border-t border-slate-200 px-4 py-3 dark:border-slate-800">
            {paginator.links.map((link) =>
                link.url ? (
                    <Link
                        key={link.label}
                        href={link.url}
                        className={`rounded-md px-3 py-1.5 text-sm ${
                            link.active
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                        }`}
                        preserveScroll
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={link.label}
                        className="rounded-md px-3 py-1.5 text-sm text-slate-400"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </nav>
    );
}
