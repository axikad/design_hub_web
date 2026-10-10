import { AdminPageHeader } from '@/components/admin/page-header';
import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type Row = {
    id: string;
    name: string;
    categoryName: string | null;
    designsCount: number;
    isActive: boolean;
};

type Props = { types: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] } };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Types', href: '/admin/types' },
];

export default function AdminTypesIndex({ types }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Types" />
            <div className="space-y-6">
                <AdminPageHeader title="Types" description="Types belong to a category and are linked to each design." createHref={route('admin.types.create')} createLabel="Add type" />
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                {['Type', 'Category', 'Designs', 'Status', 'Actions'].map((h) => (
                                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {types.data.map((row) => (
                                <tr key={row.id}>
                                    <td className="px-4 py-3 text-sm font-medium">{row.name}</td>
                                    <td className="px-4 py-3 text-sm text-slate-600">{row.categoryName ?? '—'}</td>
                                    <td className="px-4 py-3 text-sm">{row.designsCount}</td>
                                    <td className="px-4 py-3 text-sm">{row.isActive ? 'Active' : 'Hidden'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={route('admin.types.edit', row.id)}>Edit</Link>
                                            </Button>
                                            <ConfirmDelete
                                                title="Delete type?"
                                                description="Reassign designs using this type first."
                                                onConfirm={() => router.delete(route('admin.types.destroy', row.id))}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <PaginationLinks paginator={types} />
                </div>
            </div>
        </AppLayout>
    );
}
