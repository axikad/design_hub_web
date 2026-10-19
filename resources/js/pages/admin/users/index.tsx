import { AdminEmptyState } from '@/components/admin/admin-empty-state';
import { AdminFilterBar } from '@/components/admin/admin-filter-bar';
import { AdminSearchInput } from '@/components/admin/admin-search-input';
import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminTablePanel } from '@/components/admin/admin-table-panel';
import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, RotateCcw, Search, UserPlus, UsersRound } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type UserRow = {
    id: string;
    name: string | null;
    email: string | null;
    roleName: string | null;
    roleSlug: string | null;
    status: 'active' | 'inactive';
    lastLoginAt: string | null;
    countryCode: string;
    mobileNumber: string;
    designsCount?: number;
};

type RoleOption = {
    id: number;
    name: string;
    slug: string;
};

type Props = {
    users: {
        data: UserRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    roles: RoleOption[];
    filters: {
        search: string;
        role: string;
        status: string;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Users', href: '/admin/users' },
];

const initials = (name: string | null) =>
    (name ?? 'User')
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();

const formatDate = (value: string | null) => {
    if (!value) return 'Never';
    return new Intl.DateTimeFormat('en-IN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
};

export default function AdminUsersIndex({ users, roles, filters }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canCreate = permissions.includes('users.create');
    const canUpdate = permissions.includes('users.update');
    const canDelete = permissions.includes('users.delete');
    const [search, setSearch] = useState(filters.search);
    const [role, setRole] = useState(filters.role || 'all');
    const [status, setStatus] = useState(filters.status || 'all');

    const submitFilters: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(
            route('admin.users.index'),
            { search: search || undefined, role: role === 'all' ? undefined : role, status: status === 'all' ? undefined : status },
            { preserveState: true, replace: true },
        );
    };

    const resetFilters = () => {
        setSearch('');
        setRole('all');
        setStatus('all');
        router.get(route('admin.users.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="User management"
                    description="Manage marketplace users, panel members, roles, account access and activity."
                    createHref={canCreate ? route('admin.users.create') : undefined}
                    createLabel="Add user"
                />

                <form onSubmit={submitFilters}>
                    <AdminFilterBar
                        actions={
                            <>
                                <Button type="button" variant="ghost" className="rounded-xl" onClick={resetFilters}>
                                    <RotateCcw className="size-4" />
                                    Reset
                                </Button>
                                <Button type="submit" className="rounded-xl bg-indigo-600 hover:bg-indigo-700">
                                    <Search className="size-4" />
                                    Apply
                                </Button>
                            </>
                        }
                    >
                        <AdminSearchInput
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onClear={() => setSearch('')}
                            placeholder="Search name, email or mobile..."
                        />
                        <Select value={role} onValueChange={setRole}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-44 dark:border-slate-700">
                                <SelectValue placeholder="All roles" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All roles</SelectItem>
                                {roles.map((option) => (
                                    <SelectItem key={option.id} value={option.slug}>
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-36 dark:border-slate-700">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </AdminFilterBar>
                </form>

                <AdminTablePanel footer={<PaginationLinks paginator={users} />}>
                    {users.data.length === 0 ? (
                        <AdminEmptyState
                            icon={UsersRound}
                            title="No users found"
                            description="Try changing your filters or add a new user to the DesignHub workspace."
                            action={
                                canCreate ? (
                                    <Button asChild className="rounded-xl bg-indigo-600 hover:bg-indigo-700">
                                        <Link href={route('admin.users.create')}>
                                            <UserPlus className="size-4" />
                                            Add user
                                        </Link>
                                    </Button>
                                ) : undefined
                            }
                        />
                    ) : (
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead className="bg-slate-50/80 dark:bg-slate-950/60">
                                <tr>
                                    {['User', 'Role', 'Contact', 'Status', 'Designs', 'Last login', 'Actions'].map((heading) => (
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
                                {users.data.map((user) => (
                                    <tr key={user.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10">
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-10 rounded-xl">
                                                    <AvatarFallback className="rounded-xl bg-indigo-100 text-xs font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                                        {initials(user.name)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                                        {user.name ?? 'Unnamed user'}
                                                    </p>
                                                    <p className="mt-0.5 text-xs text-slate-400">{user.id}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <AdminStatusBadge tone={user.roleSlug === 'super_admin' ? 'info' : 'neutral'} dot={false}>
                                                {user.roleName ?? 'No role'}
                                            </AdminStatusBadge>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                            <p>{user.email ?? 'No email'}</p>
                                            <p className="mt-1 text-xs text-slate-400">
                                                {user.mobileNumber ? `${user.countryCode} ${user.mobileNumber}` : 'No mobile number'}
                                            </p>
                                        </td>
                                        <td className="px-5 py-4">
                                            <AdminStatusBadge tone={user.status === 'active' ? 'success' : 'danger'}>
                                                {user.status === 'active' ? 'Active' : 'Inactive'}
                                            </AdminStatusBadge>
                                        </td>
                                        <td className="px-5 py-4 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            {user.designsCount ?? 0}
                                        </td>
                                        <td className="px-5 py-4 text-xs whitespace-nowrap text-slate-500 dark:text-slate-400">
                                            {formatDate(user.lastLoginAt)}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-2">
                                                {canUpdate ? (
                                                    <Button asChild variant="outline" size="sm" className="rounded-lg">
                                                        <Link href={route('admin.users.edit', user.id)}>
                                                            <Pencil className="size-3.5" />
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                {canDelete && user.roleSlug !== 'super_admin' ? (
                                                    <ConfirmDelete
                                                        title="Delete user?"
                                                        description="This permanently removes the user and cannot be undone."
                                                        onConfirm={() => router.delete(route('admin.users.destroy', user.id))}
                                                    />
                                                ) : null}
                                                {!canUpdate && !canDelete ? <span className="text-xs text-slate-400">View only</span> : null}
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
