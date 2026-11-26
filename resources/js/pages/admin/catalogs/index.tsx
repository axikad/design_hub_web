import { AdminEmptyState } from '@/components/admin/admin-empty-state';
import { AdminFilterBar } from '@/components/admin/admin-filter-bar';
import { AdminSearchInput } from '@/components/admin/admin-search-input';
import { AdminTablePanel } from '@/components/admin/admin-table-panel';
import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Archive, Eye, Pencil, RotateCcw, Search } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type CatalogRow = {
    id: string;
    name: string;
    ownerName: string | null;
    ownerPhone: string | null;
    priceRupees: string;
    zipName: string;
    fileCount: number;
    totalSize: number;
    paidPurchaseCount: number;
    revenuePaise: number;
    updatedAt: string | null;
};

type Props = {
    catalogs: { data: CatalogRow[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search: string };
    ownContentOnly: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Catalogs', href: '/admin/catalogs' },
];

const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });

function formatBytes(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unit = units[0];

    for (let index = 1; value >= 1024 && index < units.length; index++) {
        value /= 1024;
        unit = units[index];
    }

    return `${value.toFixed(value >= 10 ? 1 : 2)} ${unit}`;
}

export default function AdminCatalogsIndex({ catalogs, filters, ownContentOnly }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canCreate = permissions.includes('catalogs.create');
    const canUpdate = permissions.includes('catalogs.update');
    const canDelete = permissions.includes('catalogs.delete');
    const [search, setSearch] = useState(filters.search);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(route('admin.catalogs.index'), { search: search || undefined }, { preserveState: true, replace: true });
    };

    const reset = () => {
        setSearch('');
        router.get(route('admin.catalogs.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Catalogs" />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title={ownContentOnly ? 'My catalogs' : 'Catalogs'}
                    description={
                        ownContentOnly
                            ? 'Upload and manage your priced ZIP or RAR packages.'
                            : 'Upload priced ZIP or RAR packages and manage the files extracted from each archive.'
                    }
                    createHref={canCreate ? route('admin.catalogs.create') : undefined}
                    createLabel="Add catalog"
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
                            placeholder="Search catalog or archive name..."
                        />
                    </AdminFilterBar>
                </form>

                <AdminTablePanel footer={<PaginationLinks paginator={catalogs} />}>
                    {catalogs.data.length === 0 ? (
                        <AdminEmptyState icon={Archive} title="No catalogs found" description="Add a ZIP or RAR package to create the first catalog." />
                    ) : (
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead className="bg-slate-50/80 dark:bg-slate-950/60">
                                <tr>
                                    {[
                                        'Catalog',
                                        ...(!ownContentOnly ? ['Seller'] : []),
                                        'Price',
                                        'Purchases',
                                        'Revenue',
                                        'Archive',
                                        'Files',
                                        'Total size',
                                        'Actions',
                                    ].map((heading) => (
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
                                {catalogs.data.map((catalog) => (
                                    <tr key={catalog.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10">
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                                                    <Archive className="size-4.5" />
                                                </span>
                                                <div>
                                                    <p className="text-sm font-semibold text-slate-900 dark:text-white">{catalog.name}</p>
                                                    <p className="mt-0.5 text-xs text-slate-400">{catalog.id}</p>
                                                </div>
                                            </div>
                                        </td>
                                        {!ownContentOnly ? (
                                            <td className="px-5 py-4">
                                                <p className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                                    {catalog.ownerName || 'Unassigned'}
                                                </p>
                                                <p className="mt-0.5 text-xs text-slate-400">{catalog.ownerPhone || 'No phone'}</p>
                                            </td>
                                        ) : null}
                                        <td className="px-5 py-4 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            {money.format(Number(catalog.priceRupees))}
                                        </td>
                                        <td className="px-5 py-4 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            {catalog.paidPurchaseCount}
                                        </td>
                                        <td className="px-5 py-4 text-sm font-semibold text-emerald-600 dark:text-emerald-300">
                                            {money.format(catalog.revenuePaise / 100)}
                                        </td>
                                        <td className="max-w-64 px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                            <span className="block truncate" title={catalog.zipName}>
                                                {catalog.zipName}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4 text-sm font-semibold text-slate-700 dark:text-slate-200">{catalog.fileCount}</td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">{formatBytes(catalog.totalSize)}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex gap-2">
                                                <Button variant="outline" size="sm" asChild className="rounded-lg">
                                                    <Link href={route('admin.catalogs.show', catalog.id)}>
                                                        <Eye className="size-4" /> View
                                                    </Link>
                                                </Button>
                                                {canUpdate ? (
                                                    <Button variant="outline" size="sm" asChild className="rounded-lg">
                                                        <Link href={route('admin.catalogs.edit', catalog.id)}>
                                                            <Pencil className="size-4" /> Edit
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                {canDelete ? (
                                                    <ConfirmDelete
                                                        title={`Delete ${catalog.name}?`}
                                                        description="The original archive and every extracted file will be permanently deleted."
                                                        onConfirm={() => router.delete(route('admin.catalogs.destroy', catalog.id))}
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
