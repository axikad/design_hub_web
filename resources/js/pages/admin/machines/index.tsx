import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type Row = { id: string; name: string; machineTypesCount: number; isActive: boolean; sortOrder: number };
type Props = { machines: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] } };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Machines', href: '/admin/machines' },
];

export default function AdminMachinesIndex({ machines }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Machines" />
            <div className="space-y-6">
                <AdminPageHeader
                    title="Machines"
                    description="Manage machines available in the design catalog."
                    createHref={route('admin.machines.create')}
                    createLabel="Add machine"
                />
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                {['Machine', 'Machine types', 'Sort order', 'Status', 'Actions'].map((heading) => (
                                    <th key={heading} className="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {machines.data.map((machine) => (
                                <tr key={machine.id}>
                                    <td className="px-4 py-3 text-sm font-medium">{machine.name}</td>
                                    <td className="px-4 py-3 text-sm">{machine.machineTypesCount}</td>
                                    <td className="px-4 py-3 text-sm">{machine.sortOrder}</td>
                                    <td className="px-4 py-3 text-sm">{machine.isActive ? 'Active' : 'Hidden'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={route('admin.machines.edit', machine.id)}>Edit</Link>
                                            </Button>
                                            <ConfirmDelete
                                                title="Delete machine?"
                                                description="Delete or reassign its machine types first."
                                                onConfirm={() => router.delete(route('admin.machines.destroy', machine.id))}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <PaginationLinks paginator={machines} />
                </div>
            </div>
        </AppLayout>
    );
}
