import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type Row = { id: string; name: string; machineName: string | null; categoryNames: string[]; isActive: boolean; sortOrder: number };
type Props = { machineTypes: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] } };
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Machine Types', href: '/admin/machine-types' },
];

export default function AdminMachineTypesIndex({ machineTypes }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Machine Types" />
            <div className="space-y-6">
                <AdminPageHeader
                    title="Machine Types"
                    description="Connect each machine type to one machine and its compatible categories."
                    createHref={route('admin.machine-types.create')}
                    createLabel="Add machine type"
                />
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                {['Machine type', 'Machine', 'Categories', 'Status', 'Actions'].map((heading) => (
                                    <th key={heading} className="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {machineTypes.data.map((machineType) => (
                                <tr key={machineType.id}>
                                    <td className="px-4 py-3 text-sm font-medium">{machineType.name}</td>
                                    <td className="px-4 py-3 text-sm">{machineType.machineName ?? '—'}</td>
                                    <td className="max-w-sm px-4 py-3 text-sm text-slate-600">{machineType.categoryNames.join(', ') || 'None'}</td>
                                    <td className="px-4 py-3 text-sm">{machineType.isActive ? 'Active' : 'Hidden'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={route('admin.machine-types.edit', machineType.id)}>Edit</Link>
                                            </Button>
                                            <ConfirmDelete
                                                title="Delete machine type?"
                                                description="Reassign designs using this machine type first."
                                                onConfirm={() => router.delete(route('admin.machine-types.destroy', machineType.id))}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <PaginationLinks paginator={machineTypes} />
                </div>
            </div>
        </AppLayout>
    );
}
