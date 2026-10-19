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
import { Cog, Pencil, RotateCcw, Search } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type Row = { id: string; name: string; machineName: string | null; categoryNames: string[]; isActive: boolean; sortOrder: number };
type Option = { id: string; name: string };
type Props = {
    machineTypes: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] };
    machines: Option[];
    filters: { search: string; status: string; machine: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Machine Types', href: '/admin/machine-types' },
];

export default function AdminMachineTypesIndex({ machineTypes, machines, filters }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canCreate = permissions.includes('machine_types.create');
    const canUpdate = permissions.includes('machine_types.update');
    const canDelete = permissions.includes('machine_types.delete');
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');
    const [machine, setMachine] = useState(filters.machine || 'all');

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(
            route('admin.machine-types.index'),
            {
                search: search || undefined,
                status: status === 'all' ? undefined : status,
                machine: machine === 'all' ? undefined : machine,
            },
            { preserveState: true, replace: true },
        );
    };

    const reset = () => {
        setSearch('');
        setStatus('all');
        setMachine('all');
        router.get(route('admin.machine-types.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Machine Types" />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="Machine Types"
                    description="Connect each machine type to one machine and its compatible categories."
                    createHref={canCreate ? route('admin.machine-types.create') : undefined}
                    createLabel="Add machine type"
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
                            placeholder="Search machine types..."
                        />
                        <Select value={machine} onValueChange={setMachine}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-48 dark:border-slate-700">
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

                <AdminTablePanel footer={<PaginationLinks paginator={machineTypes} />}>
                    {machineTypes.data.length === 0 ? (
                        <AdminEmptyState
                            icon={Cog}
                            title="No machine types found"
                            description="Change your filters or add the first machine type to the catalog."
                        />
                    ) : (
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead className="bg-slate-50/80 dark:bg-slate-950/60">
                                <tr>
                                    {['Machine type', 'Machine', 'Categories', 'Sort order', 'Status', 'Actions'].map((heading) => (
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
                                {machineTypes.data.map((machineType) => (
                                    <tr key={machineType.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10">
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                                                    <Cog className="size-4.5" />
                                                </span>
                                                <div>
                                                    <p className="text-sm font-semibold text-slate-900 dark:text-white">{machineType.name}</p>
                                                    <p className="mt-0.5 text-xs text-slate-400">{machineType.id}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            {machineType.machineName ?? 'Not assigned'}
                                        </td>
                                        <td className="max-w-sm px-5 py-4">
                                            <div className="flex flex-wrap gap-1.5">
                                                {machineType.categoryNames.length ? (
                                                    machineType.categoryNames.map((category) => (
                                                        <AdminStatusBadge key={category} tone="info" dot={false}>
                                                            {category}
                                                        </AdminStatusBadge>
                                                    ))
                                                ) : (
                                                    <span className="text-sm text-slate-400">None</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">{machineType.sortOrder}</td>
                                        <td className="px-5 py-4">
                                            <AdminStatusBadge tone={machineType.isActive ? 'success' : 'neutral'}>
                                                {machineType.isActive ? 'Active' : 'Hidden'}
                                            </AdminStatusBadge>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex gap-2">
                                                {canUpdate ? (
                                                    <Button asChild variant="outline" size="sm" className="rounded-lg">
                                                        <Link href={route('admin.machine-types.edit', machineType.id)}>
                                                            <Pencil className="size-3.5" /> Edit
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                {canDelete ? (
                                                    <ConfirmDelete
                                                        title="Delete machine type?"
                                                        description="Reassign designs using this machine type first."
                                                        onConfirm={() => router.delete(route('admin.machine-types.destroy', machineType.id))}
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
