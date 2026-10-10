import { AdminPageHeader } from '@/components/admin/page-header';
import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type Row = { id: string; name: string; description: string | null; isActive: boolean; typesCount: number };

type Props = { categories: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] } };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Categories', href: '/admin/categories' },
];

export default function AdminCategoriesIndex({ categories }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categories" />
            <div className="space-y-6">
                <AdminPageHeader title="Categories" description="Top-level groups for design types." createHref={route('admin.categories.create')} createLabel="Add category" />
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                {['Name', 'Description', 'Types', 'Status', 'Actions'].map((h) => (
                                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {categories.data.map((row) => (
                                <tr key={row.id}>
                                    <td className="px-4 py-3 text-sm font-medium">{row.name}</td>
                                    <td className="px-4 py-3 text-sm text-slate-600">{row.description ?? '—'}</td>
                                    <td className="px-4 py-3 text-sm">{row.typesCount}</td>
                                    <td className="px-4 py-3 text-sm">{row.isActive ? 'Active' : 'Hidden'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={route('admin.categories.edit', row.id)}>Edit</Link>
                                            </Button>
                                            <ConfirmDelete
                                                title="Delete category?"
                                                description="Delete all types in this category first."
                                                onConfirm={() => router.delete(route('admin.categories.destroy', row.id))}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <PaginationLinks paginator={categories} />
                </div>
            </div>
        </AppLayout>
    );
}
