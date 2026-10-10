import { AdminPageHeader } from '@/components/admin/page-header';
import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type DesignRow = {
    id: string;
    title: string;
    pricePaise: number | null;
    size: string;
    color: string;
    imageUri: string | null;
    ownerKey: string | null;
    ownerName?: string | null;
    categoryName?: string | null;
    typeName?: string | null;
    createdAt: string | null;
};

type Props = {
    designs: {
        data: DesignRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Designs', href: '/admin/designs' },
];

function formatInr(paise: number | null): string {
    if (paise === null) return '—';
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(paise / 100);
}

export default function AdminDesignsIndex({ designs }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Designs" />
            <div className="space-y-6">
                <AdminPageHeader
                    title="Designs"
                    description="Create, edit, and remove marketplace embroidery listings."
                    createHref={route('admin.designs.create')}
                    createLabel="Add design"
                />
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                {['Preview', 'Title', 'Category', 'Type', 'Price', 'Owner', 'Actions'].map((heading) => (
                                    <th key={heading} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {designs.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-10 text-center text-sm text-slate-500">
                                        No designs yet.{' '}
                                        <Link href={route('admin.designs.create')} className="font-medium text-indigo-600 hover:underline">
                                            Add the first design
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                designs.data.map((design) => (
                                    <tr key={design.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                                        <td className="px-4 py-3">
                                            {design.imageUri ? (
                                                <img src={design.imageUri} alt="" className="h-12 w-12 rounded-lg object-cover ring-1 ring-slate-200 dark:ring-slate-700" />
                                            ) : (
                                                <span className="text-xs text-slate-400">No image</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm font-medium text-slate-900 dark:text-slate-100">{design.title}</td>
                                        <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{design.categoryName ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{design.typeName ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{formatInr(design.pricePaise)}</td>
                                        <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                            {design.ownerName ?? design.ownerKey ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-2">
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={route('admin.designs.edit', design.id)}>Edit</Link>
                                                </Button>
                                                <ConfirmDelete
                                                    title="Remove design?"
                                                    description="This hides the design from the marketplace. Purchased files follow your retention policy."
                                                    onConfirm={() => router.delete(route('admin.designs.destroy', design.id))}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                    <PaginationLinks paginator={designs} />
                </div>
            </div>
        </AppLayout>
    );
}
