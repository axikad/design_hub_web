import { AdminPageHeader } from '@/components/admin/page-header';
import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type UserRow = {
    id: string;
    name: string | null;
    email: string | null;
    roleName: string | null;
    countryCode: string;
    mobileNumber: string;
    designsCount?: number;
};

type Props = {
    users: {
        data: UserRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Users', href: '/admin/users' },
];

export default function AdminUsersIndex({ users }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />
            <div className="space-y-6">
                <AdminPageHeader
                    title="Marketplace users"
                    description="Register and manage mobile app sellers and buyers."
                    createHref={route('admin.users.create')}
                    createLabel="Add user"
                />
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                {['Name', 'Role', 'Email', 'Mobile', 'Designs', 'Actions'].map((heading) => (
                                    <th key={heading} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {users.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-sm text-slate-500">
                                        No users yet.{' '}
                                        <Link href={route('admin.users.create')} className="font-medium text-indigo-600 hover:underline">
                                            Add a user
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                users.data.map((user) => (
                                    <tr key={user.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                                        <td className="px-4 py-3 text-sm font-medium text-slate-900 dark:text-slate-100">{user.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{user.roleName ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{user.email ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                            {user.mobileNumber ? `${user.countryCode} ${user.mobileNumber}` : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{user.designsCount ?? 0}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-2">
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={route('admin.users.edit', user.id)}>Edit</Link>
                                                </Button>
                                                <ConfirmDelete
                                                    title="Delete user?"
                                                    description="This permanently removes the user and their related data."
                                                    onConfirm={() => router.delete(route('admin.users.destroy', user.id))}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                    <PaginationLinks paginator={users} />
                </div>
            </div>
        </AppLayout>
    );
}
