import { AdminEmptyState } from '@/components/admin/admin-empty-state';
import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminSearchInput } from '@/components/admin/admin-search-input';
import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { KeyRound, LoaderCircle, Plus, Save, SearchX, ShieldCheck, UsersRound } from 'lucide-react';
import { type FormEventHandler, useMemo, useState } from 'react';

type RoleRow = { id: number; slug: string; name: string };
type PermissionRow = { id: number; slug: string; name: string; action: string; module: string | null };

type Props = {
    roles: RoleRow[];
    permissions: PermissionRow[];
    assignedPermissionIds: Record<number, number[]>;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Roles & permissions', href: '/admin/roles' },
];

export default function AdminRolesIndex({ roles, permissions, assignedPermissionIds }: Props) {
    const authPermissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canCreate = authPermissions.includes('roles.create');
    const canUpdate = authPermissions.includes('roles.assign_permissions');
    const canDelete = authPermissions.includes('roles.delete');
    const [activeRoleId, setActiveRoleId] = useState<number>(roles[0]?.id ?? 0);
    const [search, setSearch] = useState('');
    const activeRole = useMemo(() => roles.find((role) => role.id === activeRoleId) ?? roles[0], [activeRoleId, roles]);
    const locked = activeRole?.slug === 'super_admin';

    const createForm = useForm({ name: '' });
    const permissionForm = useForm<{ permissionIds: number[] }>({
        permissionIds: assignedPermissionIds[activeRole?.id ?? 0] ?? [],
    });

    const groupedPermissions = useMemo(() => {
        const query = search.trim().toLowerCase();
        return permissions.reduce<Record<string, PermissionRow[]>>((groups, permission) => {
            if (query && !`${permission.name} ${permission.slug} ${permission.module ?? ''} ${permission.action}`.toLowerCase().includes(query)) {
                return groups;
            }
            const module = permission.module ?? 'Other';
            groups[module] = [...(groups[module] ?? []), permission];
            return groups;
        }, {});
    }, [permissions, search]);

    const createRole: FormEventHandler = (event) => {
        event.preventDefault();
        createForm.post(route('admin.roles.store'), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const savePermissions: FormEventHandler = (event) => {
        event.preventDefault();
        if (!activeRole || locked || !canUpdate) return;
        router.put(route('admin.roles.permissions.update', activeRole.id), {
            permissionIds: permissionForm.data.permissionIds,
        });
    };

    const changeRole = (roleId: number) => {
        setActiveRoleId(roleId);
        permissionForm.setData('permissionIds', assignedPermissionIds[roleId] ?? []);
    };

    const togglePermission = (permissionId: number, checked: boolean) => {
        permissionForm.setData(
            'permissionIds',
            checked
                ? [...new Set([...permissionForm.data.permissionIds, permissionId])]
                : permissionForm.data.permissionIds.filter((id) => id !== permissionId),
        );
    };

    const toggleModule = (modulePermissions: PermissionRow[], checked: boolean) => {
        const moduleIds = modulePermissions.map((permission) => permission.id);
        permissionForm.setData(
            'permissionIds',
            checked
                ? [...new Set([...permissionForm.data.permissionIds, ...moduleIds])]
                : permissionForm.data.permissionIds.filter((id) => !moduleIds.includes(id)),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles & permissions" />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader title="Roles & permissions" description="Control exactly what each administrator role can access and manage." />

                <div className="grid gap-5 xl:grid-cols-[280px_1fr]">
                    <aside className="space-y-5">
                        {canCreate ? (
                            <AdminFormPanel title="Create role" description="Add a new access profile for your team." icon={UsersRound}>
                                <form onSubmit={createRole} className="space-y-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="roleName">Role name</Label>
                                        <Input
                                            id="roleName"
                                            value={createForm.data.name}
                                            onChange={(event) => createForm.setData('name', event.target.value)}
                                            placeholder="Content manager"
                                            className="h-10 rounded-xl border-slate-200 dark:border-slate-700"
                                        />
                                        <InputError message={createForm.errors.name} />
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={createForm.processing}
                                        className="w-full rounded-xl bg-indigo-600 hover:bg-indigo-700"
                                    >
                                        {createForm.processing ? <LoaderCircle className="size-4 animate-spin" /> : <Plus className="size-4" />}
                                        Add role
                                    </Button>
                                </form>
                            </AdminFormPanel>
                        ) : null}

                        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div className="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                <p className="text-xs font-bold tracking-wider text-slate-500 uppercase">Available roles</p>
                            </div>
                            <div className="space-y-1 p-2">
                                {roles.map((role) => {
                                    const selected = role.id === activeRole?.id;
                                    const count = assignedPermissionIds[role.id]?.length ?? 0;
                                    return (
                                        <button
                                            key={role.id}
                                            type="button"
                                            onClick={() => changeRole(role.id)}
                                            className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition ${
                                                selected
                                                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20'
                                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                                            }`}
                                        >
                                            <ShieldCheck className="size-4 shrink-0" />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-semibold">{role.name}</span>
                                                <span className={`block text-xs ${selected ? 'text-indigo-100' : 'text-slate-400'}`}>
                                                    {count} permissions
                                                </span>
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        </section>
                    </aside>

                    {activeRole ? (
                        <form
                            onSubmit={savePermissions}
                            className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                        >
                            <div className="flex flex-col gap-4 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                                        <KeyRound className="size-5" />
                                    </div>
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="font-bold text-slate-900 dark:text-white">{activeRole.name}</h2>
                                            {locked ? <AdminStatusBadge tone="info">Protected</AdminStatusBadge> : null}
                                        </div>
                                        <p className="mt-0.5 text-xs text-slate-400">{activeRole.slug}</p>
                                    </div>
                                </div>
                                {canDelete && !locked ? (
                                    <ConfirmDelete
                                        title="Delete role?"
                                        description="Users must be reassigned before this role can be removed."
                                        onConfirm={() => router.delete(route('admin.roles.destroy', activeRole.id))}
                                    />
                                ) : null}
                            </div>

                            <div className="border-b border-slate-100 p-4 dark:border-slate-800">
                                <AdminSearchInput
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    onClear={() => setSearch('')}
                                    placeholder="Search modules or permissions..."
                                />
                            </div>

                            <div className="space-y-4 p-4 sm:p-5">
                                {Object.keys(groupedPermissions).length === 0 ? (
                                    <AdminEmptyState
                                        icon={SearchX}
                                        title="No permissions found"
                                        description="Try another module, action or permission name."
                                    />
                                ) : (
                                    Object.entries(groupedPermissions).map(([module, modulePermissions]) => {
                                        const allSelected =
                                            locked ||
                                            modulePermissions.every((permission) => permissionForm.data.permissionIds.includes(permission.id));
                                        return (
                                            <section
                                                key={module}
                                                className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800"
                                            >
                                                <div className="flex items-center justify-between bg-slate-50 px-4 py-3 dark:bg-slate-950/60">
                                                    <div>
                                                        <h3 className="text-sm font-bold text-slate-800 dark:text-slate-100">{module}</h3>
                                                        <p className="text-xs text-slate-400">{modulePermissions.length} available actions</p>
                                                    </div>
                                                    <label className="flex items-center gap-2 text-xs font-semibold text-slate-500">
                                                        <Checkbox
                                                            checked={allSelected}
                                                            disabled={locked || !canUpdate}
                                                            onCheckedChange={(checked) => toggleModule(modulePermissions, checked === true)}
                                                        />
                                                        Select all
                                                    </label>
                                                </div>
                                                <div className="grid gap-px bg-slate-100 sm:grid-cols-2 lg:grid-cols-4 dark:bg-slate-800">
                                                    {modulePermissions.map((permission) => (
                                                        <label
                                                            key={permission.id}
                                                            className="flex cursor-pointer items-start gap-3 bg-white p-4 transition hover:bg-indigo-50/50 dark:bg-slate-900 dark:hover:bg-indigo-950/20"
                                                        >
                                                            <Checkbox
                                                                checked={locked || permissionForm.data.permissionIds.includes(permission.id)}
                                                                disabled={locked || !canUpdate}
                                                                onCheckedChange={(checked) => togglePermission(permission.id, checked === true)}
                                                            />
                                                            <span className="min-w-0">
                                                                <span className="block text-sm font-semibold text-slate-800 capitalize dark:text-slate-100">
                                                                    {permission.action.replaceAll('_', ' ')}
                                                                </span>
                                                                <span className="mt-1 block truncate text-xs text-slate-400">{permission.slug}</span>
                                                            </span>
                                                        </label>
                                                    ))}
                                                </div>
                                            </section>
                                        );
                                    })
                                )}
                            </div>

                            <div className="flex items-center justify-between gap-4 border-t border-slate-200 bg-slate-50/60 px-5 py-4 dark:border-slate-800 dark:bg-slate-950/40">
                                <p className="text-xs text-slate-500">
                                    {locked
                                        ? 'Super Admin always has every permission.'
                                        : `${permissionForm.data.permissionIds.length} permissions selected`}
                                </p>
                                {canUpdate && !locked ? (
                                    <Button type="submit" className="rounded-xl bg-indigo-600 px-5 font-semibold hover:bg-indigo-700">
                                        <Save className="size-4" />
                                        Save permissions
                                    </Button>
                                ) : null}
                            </div>
                        </form>
                    ) : (
                        <AdminEmptyState icon={ShieldCheck} title="No roles available" description="Create a role to begin assigning permissions." />
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
