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
import { ImageIcon, Pencil, RotateCcw, Search } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type Option = { id: string; name?: string; label?: string; extension?: string };
type DesignRow = {
    id: string;
    title: string;
    displayName?: string | null;
    pricePaise: number | null;
    thumbnailUri: string | null;
    ownerKey: string | null;
    ownerName?: string | null;
    categoryName?: string | null;
    typeName?: string | null;
    machineName?: string | null;
    machineTypeName?: string | null;
    designFileTypeName?: string | null;
    createdAt: string | null;
};
type Props = {
    designs: { data: DesignRow[]; links: { url: string | null; label: string; active: boolean }[] };
    owners: Option[];
    machines: Option[];
    fileTypes: Option[];
    filters: { search: string; owner: string; machine: string; fileType: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Designs', href: '/admin/designs' },
];

function formatInr(paise: number | null): string {
    if (paise === null) return 'Not set';
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(paise / 100);
}

export default function AdminDesignsIndex({ designs, owners, machines, fileTypes, filters }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canCreate = permissions.includes('designs.create');
    const canUpdate = permissions.includes('designs.update');
    const canDelete = permissions.includes('designs.delete');
    const [search, setSearch] = useState(filters.search);
    const [owner, setOwner] = useState(filters.owner || 'all');
    const [machine, setMachine] = useState(filters.machine || 'all');
    const [fileType, setFileType] = useState(filters.fileType || 'all');

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(
            route('admin.designs.index'),
            {
                search: search || undefined,
                owner: owner === 'all' ? undefined : owner,
                machine: machine === 'all' ? undefined : machine,
                fileType: fileType === 'all' ? undefined : fileType,
            },
            { preserveState: true, replace: true },
        );
    };

    const reset = () => {
        setSearch('');
        setOwner('all');
        setMachine('all');
        setFileType('all');
        router.get(route('admin.designs.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Designs" />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="Designs"
                    description="Create, review, and manage marketplace design listings."
                    createHref={canCreate ? route('admin.designs.create') : undefined}
                    createLabel="Add design"
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
                            placeholder="Search title, ID, file, or seller..."
                        />
                        <Select value={owner} onValueChange={setOwner}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-48 dark:border-slate-700">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All sellers</SelectItem>
                                {owners.map((option) => (
                                    <SelectItem key={option.id} value={option.id}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={machine} onValueChange={setMachine}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-44 dark:border-slate-700">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All machines</SelectItem>
                                {machines.map((option) => (
                                    <SelectItem key={option.id} value={option.id}>
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={fileType} onValueChange={setFileType}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-44 dark:border-slate-700">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All file types</SelectItem>
                                {fileTypes.map((option) => (
                                    <SelectItem key={option.id} value={option.id}>
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </AdminFilterBar>
                </form>

                <AdminTablePanel footer={<PaginationLinks paginator={designs} />}>
                    {designs.data.length === 0 ? (
                        <AdminEmptyState
                            icon={ImageIcon}
                            title="No designs found"
                            description="Change your filters or add the first marketplace design."
                        />
                    ) : (
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead className="bg-slate-50/80 dark:bg-slate-950/60">
                                <tr>
                                    {['Design', 'Machine', 'Category / Type', 'File type', 'Price', 'Seller', 'Actions'].map((heading) => (
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
                                {designs.data.map((design) => (
                                    <tr key={design.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10">
                                        <td className="px-5 py-4">
                                            <div className="flex min-w-56 items-center gap-3">
                                                {design.thumbnailUri ? (
                                                    <img
                                                        src={design.thumbnailUri}
                                                        alt=""
                                                        className="size-14 rounded-xl object-cover ring-1 ring-slate-200 dark:ring-slate-700"
                                                    />
                                                ) : (
                                                    <span className="flex size-14 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                                                        <ImageIcon className="size-5" />
                                                    </span>
                                                )}
                                                <div>
                                                    <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                                        {design.displayName || design.title}
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-400">{design.id}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                            <p className="font-semibold text-slate-800 dark:text-slate-100">{design.machineName || 'Not assigned'}</p>
                                            <p className="mt-1 text-xs text-slate-400">{design.machineTypeName || 'No machine type'}</p>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                            <p>{design.categoryName || 'Not assigned'}</p>
                                            <p className="mt-1 text-xs text-slate-400">{design.typeName || 'No type'}</p>
                                        </td>
                                        <td className="px-5 py-4">
                                            <AdminStatusBadge tone="info" dot={false}>
                                                {design.designFileTypeName || 'Not set'}
                                            </AdminStatusBadge>
                                        </td>
                                        <td className="px-5 py-4 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                            {formatInr(design.pricePaise)}
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                            {design.ownerName || design.ownerKey || 'Not assigned'}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex gap-2">
                                                {canUpdate ? (
                                                    <Button asChild variant="outline" size="sm" className="rounded-lg">
                                                        <Link href={route('admin.designs.edit', design.id)}>
                                                            <Pencil className="size-3.5" /> Edit
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                {canDelete ? (
                                                    <ConfirmDelete
                                                        title="Remove design?"
                                                        description="This hides the design from the marketplace while retaining purchase history."
                                                        onConfirm={() => router.delete(route('admin.designs.destroy', design.id))}
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
