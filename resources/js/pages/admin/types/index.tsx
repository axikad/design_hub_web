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
import { useReorderableRows } from '@/hooks/use-reorderable-rows';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { GripVertical, Pencil, RotateCcw, Search, Shapes } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type Row = {
    id: string;
    name: string;
    description: string | null;
    categoryNames: string[];
    designsCount: number;
    variantUsageCount: number;
    isActive: boolean;
    sortOrder: number;
};
type Option = { id: string; name: string };
type Props = {
    types: { data: Row[]; from: number | null; links: { url: string | null; label: string; active: boolean }[] };
    categories: Option[];
    filters: { search: string; status: string; category: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Design Types', href: '/admin/types' },
];

export default function AdminTypesIndex({ types, categories, filters }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canCreate = permissions.includes('design_types.create');
    const canUpdate = permissions.includes('design_types.update');
    const canDelete = permissions.includes('design_types.delete');
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');
    const [category, setCategory] = useState(filters.category || 'all');
    const canReorder = canUpdate && !filters.search && !filters.status && !filters.category;
    const { rows, rowDragProps, draggedId, dropTargetId } = useReorderableRows(types.data, canReorder, (ids) =>
        router.put(route('admin.types.reorder'), { ids, startPosition: types.from ?? 1 }, { preserveScroll: true }),
    );

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(
            route('admin.types.index'),
            {
                search: search || undefined,
                status: status === 'all' ? undefined : status,
                category: category === 'all' ? undefined : category,
            },
            { preserveState: true, replace: true },
        );
    };

    const reset = () => {
        setSearch('');
        setStatus('all');
        setCategory('all');
        router.get(route('admin.types.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Design Types" />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="Design Types"
                    description="Manage the specific design options available inside each category."
                    createHref={canCreate ? route('admin.types.create') : undefined}
                    createLabel="Add design type"
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
                            placeholder="Search design types..."
                        />
                        <Select value={category} onValueChange={setCategory}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-48 dark:border-slate-700">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All categories</SelectItem>
                                {categories.map((option) => (
                                    <SelectItem key={option.id} value={option.id}>
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
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

                <AdminTablePanel footer={<PaginationLinks paginator={types} />}>
                    {types.data.length === 0 ? (
                        <AdminEmptyState
                            icon={Shapes}
                            title="No design types found"
                            description="Change your filters or add the first design type to the catalog."
                        />
                    ) : (
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead className="bg-slate-50/80 dark:bg-slate-950/60">
                                <tr>
                                    {['', 'Design type', 'Category', 'Description', 'Designs', 'Position', 'Status', 'Actions'].map((heading) => (
                                        <th
                                            key={heading}
                                            className="px-5 py-3.5 text-left text-[11px] font-bold tracking-wider text-slate-500 uppercase"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {rows.map((designType) => (
                                    <tr
                                        key={designType.id}
                                        {...rowDragProps(designType.id)}
                                        className={`transition-colors hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10 ${
                                            draggedId === designType.id ? 'opacity-50' : ''
                                        } ${dropTargetId === designType.id ? 'bg-indigo-50 ring-2 ring-indigo-300 ring-inset dark:bg-indigo-950/30' : ''}`}
                                    >
                                        <td className="w-10 px-3 py-4 text-slate-400">
                                            <GripVertical className={`size-4 ${canReorder ? 'cursor-grab active:cursor-grabbing' : 'opacity-30'}`} />
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                                                    <Shapes className="size-4.5" />
                                                </span>
                                                <div>
                                                    <p className="text-sm font-semibold text-slate-900 dark:text-white">{designType.name}</p>
                                                    <p className="mt-0.5 text-xs text-slate-400">{designType.id}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex max-w-64 flex-wrap gap-1.5">
                                                {designType.categoryNames.length ? (
                                                    designType.categoryNames.map((name) => (
                                                        <AdminStatusBadge key={name} tone="info" dot={false}>
                                                            {name}
                                                        </AdminStatusBadge>
                                                    ))
                                                ) : (
                                                    <span className="text-sm text-slate-400">Not assigned</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="max-w-xs px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                            {designType.description || 'No description'}
                                        </td>
                                        <td className="px-5 py-4 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            <div>{Math.max(designType.designsCount, designType.variantUsageCount)}</div>
                                            {designType.variantUsageCount > 0 ? (
                                                <div className="mt-0.5 text-xs font-normal text-slate-400">
                                                    {designType.variantUsageCount} type {designType.variantUsageCount === 1 ? 'connection' : 'connections'}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">{designType.sortOrder}</td>
                                        <td className="px-5 py-4">
                                            <AdminStatusBadge tone={designType.isActive ? 'success' : 'neutral'}>
                                                {designType.isActive ? 'Active' : 'Hidden'}
                                            </AdminStatusBadge>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex gap-2">
                                                {canUpdate ? (
                                                    <Button asChild variant="outline" size="sm" className="rounded-lg">
                                                        <Link href={route('admin.types.edit', designType.id)}>
                                                            <Pencil className="size-3.5" /> Edit
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                {canDelete ? (
                                                    <ConfirmDelete
                                                        title={`Delete ${designType.name}?`}
                                                        description={
                                                            designType.designsCount + designType.variantUsageCount > 0
                                                                ? `This type has ${designType.designsCount} direct design connection(s) and ${designType.variantUsageCount} variant connection(s). The designs will be kept, these connections will be removed, and “${designType.name}” will be preserved as their display label.`
                                                                : 'This type is not connected to any design records and can be safely deleted.'
                                                        }
                                                        onConfirm={() =>
                                                            router.delete(route('admin.types.destroy', designType.id), { preserveScroll: true })
                                                        }
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
