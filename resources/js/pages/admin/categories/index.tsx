import { AdminEmptyState } from '@/components/admin/admin-empty-state';
import { AdminFilterBar } from '@/components/admin/admin-filter-bar';
import { AdminSearchInput } from '@/components/admin/admin-search-input';
import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminTablePanel } from '@/components/admin/admin-table-panel';
import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FolderTree, Pencil, RotateCcw, Search } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type Row = {
    id: string;
    name: string;
    description: string | null;
    isActive: boolean;
    sortOrder: number;
    typesCount: number;
    machineTypesCount: number;
};
type Props = {
    categories: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search: string; status: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Categories', href: '/admin/categories' },
];

export default function AdminCategoriesIndex({ categories, filters }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canCreate = permissions.includes('categories.create');
    const canUpdate = permissions.includes('categories.update');
    const canDelete = permissions.includes('categories.delete');
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(
            route('admin.categories.index'),
            { search: search || undefined, status: status === 'all' ? undefined : status },
            { preserveState: true, replace: true },
        );
    };

    const reset = () => {
        setSearch('');
        setStatus('all');
        router.get(route('admin.categories.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categories" />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="Categories"
                    description="Manage the category groups used by machine types and design types."
                    createHref={canCreate ? route('admin.categories.create') : undefined}
                    createLabel="Add category"
                />

                <form onSubmit={submit}>
                    <AdminFilterBar
                        actions={
                            <>
                                <Button type="button" variant="ghost" className="rounded-xl" onClick={reset}>
                                    <RotateCcw className="size-4" /> Reset
                                </Button>
                                <Button type="submit" className="rounded-xl bg-indigo-600 hover:bg-indigo-700">
                                    <Search className="size-4" /> Apply
                                </Button>
                            </>
                        }
                    >
                        <AdminSearchInput
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onClear={() => setSearch('')}
                            placeholder="Search categories..."
                        />
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-40 dark:border-slate-700">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Hidden</SelectItem>
                            </SelectContent>
                        </Select>
                    </AdminFilterBar>
                </form>

                <AdminTablePanel footer={<PaginationLinks paginator={categories} />}>
                    {categories.data.length === 0 ? (
                        <AdminEmptyState
                            icon={FolderTree}
                            title="No categories found"
                            description="Change your filters or add the first category to the catalog."
                        />
                    ) : (
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead className="bg-slate-50/80 dark:bg-slate-950/60">
                                <tr>
                                    {['Category', 'Description', 'Design types', 'Machine types', 'Sort order', 'Status', 'Actions'].map(
                                        (heading) => (
                                            <th
                                                key={heading}
                                                className="px-5 py-3.5 text-left text-[11px] font-bold tracking-wider text-slate-500 uppercase"
                                            >
                                                {heading}
                                            </th>
                                        ),
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {categories.data.map((category) => (
                                    <tr key={category.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10">
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                                                    <FolderTree className="size-4.5" />
                                                </span>
                                                <div>
                                                    <p className="text-sm font-semibold text-slate-900 dark:text-white">{category.name}</p>
                                                    <p className="mt-0.5 text-xs text-slate-400">{category.id}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="max-w-xs px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                            {category.description || 'No description'}
                                        </td>
                                        <td className="px-5 py-4 text-sm font-semibold text-slate-700 dark:text-slate-200">{category.typesCount}</td>
                                        <td className="px-5 py-4 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            {category.machineTypesCount}
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">{category.sortOrder}</td>
                                        <td className="px-5 py-4">
                                            <AdminStatusBadge tone={category.isActive ? 'success' : 'neutral'}>
                                                {category.isActive ? 'Active' : 'Hidden'}
                                            </AdminStatusBadge>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex gap-2">
                                                {canUpdate ? (
                                                    <Button asChild variant="outline" size="sm" className="rounded-lg">
                                                        <Link href={route('admin.categories.edit', category.id)}>
                                                            <Pencil className="size-3.5" /> Edit
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                {canDelete ? (
                                                    <ConfirmDelete
                                                        title="Delete category?"
                                                        description="Remove its machine type, design type, and design relationships first."
                                                        onConfirm={() => router.delete(route('admin.categories.destroy', category.id))}
                                                    />
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </AdminTablePanel>
            </div>
        </AppLayout>
    );
}
