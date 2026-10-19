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
import { FileCode2, Pencil, RotateCcw, Search } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type Row = {
    id: string;
    name: string;
    extension: string;
    designsCount: number;
    isActive: boolean;
    sortOrder: number;
};
type Props = {
    fileTypes: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search: string; status: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Design File Types', href: '/admin/design-file-types' },
];

export default function AdminDesignFileTypesIndex({ fileTypes, filters }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canCreate = permissions.includes('design_file_types.create');
    const canUpdate = permissions.includes('design_file_types.update');
    const canDelete = permissions.includes('design_file_types.delete');
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(
            route('admin.design-file-types.index'),
            { search: search || undefined, status: status === 'all' ? undefined : status },
            { preserveState: true, replace: true },
        );
    };

    const reset = () => {
        setSearch('');
        setStatus('all');
        router.get(route('admin.design-file-types.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Design File Types" />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="Design File Types"
                    description="Manage selectable stitch-file formats, extensions, and their display order."
                    createHref={canCreate ? route('admin.design-file-types.create') : undefined}
                    createLabel="Add file type"
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
                            placeholder="Search name or extension..."
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

                <AdminTablePanel footer={<PaginationLinks paginator={fileTypes} />}>
                    {fileTypes.data.length === 0 ? (
                        <AdminEmptyState
                            icon={FileCode2}
                            title="No design file types found"
                            description="Change your filters or add the first file type to the catalog."
                        />
                    ) : (
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead className="bg-slate-50/80 dark:bg-slate-950/60">
                                <tr>
                                    {['File type', 'Extension', 'Designs', 'Sort order', 'Status', 'Actions'].map((heading) => (
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
                                {fileTypes.data.map((fileType) => (
                                    <tr key={fileType.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10">
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                                                    <FileCode2 className="size-4.5" />
                                                </span>
                                                <div>
                                                    <p className="text-sm font-semibold text-slate-900 dark:text-white">{fileType.name}</p>
                                                    <p className="mt-0.5 text-xs text-slate-400">{fileType.id}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className="inline-flex rounded-lg bg-slate-100 px-2.5 py-1 font-mono text-sm font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                .{fileType.extension}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            {fileType.designsCount}
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">{fileType.sortOrder}</td>
                                        <td className="px-5 py-4">
                                            <AdminStatusBadge tone={fileType.isActive ? 'success' : 'neutral'}>
                                                {fileType.isActive ? 'Active' : 'Hidden'}
                                            </AdminStatusBadge>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex gap-2">
                                                {canUpdate ? (
                                                    <Button asChild variant="outline" size="sm" className="rounded-lg">
                                                        <Link href={route('admin.design-file-types.edit', fileType.id)}>
                                                            <Pencil className="size-3.5" /> Edit
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                {canDelete ? (
                                                    <ConfirmDelete
                                                        title="Delete design file type?"
                                                        description="Reassign designs using this file type first."
                                                        onConfirm={() => router.delete(route('admin.design-file-types.destroy', fileType.id))}
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
