import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type Row = {
    id: string;
    name: string;
    extension: string;
    designsCount: number;
    isActive: boolean;
    sortOrder: number;
};

type Props = {
    fileTypes: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Design File Types', href: '/admin/design-file-types' },
];

export default function AdminDesignFileTypesIndex({ fileTypes }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Design File Types" />
            <div className="space-y-6">
                <AdminPageHeader
                    title="Design File Types"
                    description="Manage selectable design formats and their display order."
                    createHref={route('admin.design-file-types.create')}
                    createLabel="Add file type"
                />
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                {['Name', 'Extension', 'Designs', 'Order', 'Status', 'Actions'].map((heading) => (
                                    <th key={heading} className="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {fileTypes.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-sm text-slate-500">
                                        No design file types found.
                                    </td>
                                </tr>
                            ) : (
                                fileTypes.data.map((fileType) => (
                                    <tr key={fileType.id}>
                                        <td className="px-4 py-3 text-sm font-medium">{fileType.name}</td>
                                        <td className="px-4 py-3 font-mono text-sm">.{fileType.extension}</td>
                                        <td className="px-4 py-3 text-sm">{fileType.designsCount}</td>
                                        <td className="px-4 py-3 text-sm">{fileType.sortOrder}</td>
                                        <td className="px-4 py-3 text-sm">{fileType.isActive ? 'Active' : 'Hidden'}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex gap-2">
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={route('admin.design-file-types.edit', fileType.id)}>Edit</Link>
                                                </Button>
                                                <ConfirmDelete
                                                    title="Delete design file type?"
                                                    description="Reassign designs using this file type first."
                                                    onConfirm={() => router.delete(route('admin.design-file-types.destroy', fileType.id))}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                    <PaginationLinks paginator={fileTypes} />
                </div>
            </div>
        </AppLayout>
    );
}
