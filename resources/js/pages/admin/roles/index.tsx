import { AdminPageHeader } from '@/components/admin/page-header';
import { ConfirmDelete } from '@/components/admin/confirm-delete';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useMemo, useState } from 'react';

type RoleRow = { id: number; slug: string; name: string };
type PermissionRow = { id: number; slug: string; name: string; module: string | null };
type MatrixFlags = { read: boolean; create: boolean; update: boolean; delete: boolean };

type Props = {
    roles: RoleRow[];
    permissions: PermissionRow[];
    matrix: Record<number, Record<number, MatrixFlags>>;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Roles & permissions', href: '/admin/roles' },
];

const actions: { key: keyof MatrixFlags; label: string }[] = [
    { key: 'read', label: 'View' },
    { key: 'create', label: 'Create' },
    { key: 'update', label: 'Edit' },
    { key: 'delete', label: 'Delete' },
];

export default function AdminRolesIndex({ roles, permissions, matrix }: Props) {
    const [activeRoleId, setActiveRoleId] = useState<number>(roles[0]?.id ?? 0);

    const activeRole = useMemo(() => roles.find((role) => role.id === activeRoleId) ?? roles[0], [activeRoleId, roles]);

    const createForm = useForm({ name: '' });

    const permissionForm = useForm<{ assignments: { permissionId: number; read: boolean; create: boolean; update: boolean; delete: boolean }[] }>({
        assignments: permissions.map((permission) => {
            const flags = matrix[activeRole?.id ?? 0]?.[permission.id] ?? { read: false, create: false, update: false, delete: false };
            return {
                permissionId: permission.id,
                read: flags.read,
                create: flags.create,
                update: flags.update,
                delete: flags.delete,
            };
        }),
    });

    const createRole: FormEventHandler = (e) => {
        e.preventDefault();
        createForm.post(route('admin.roles.store'), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const savePermissions: FormEventHandler = (e) => {
        e.preventDefault();
        if (!activeRole) return;
        router.put(route('admin.roles.permissions.update', activeRole.id), {
            assignments: permissionForm.data.assignments,
        });
    };

    const toggleFlag = (permissionId: number, key: keyof MatrixFlags, checked: boolean) => {
        permissionForm.setData(
            'assignments',
            permissionForm.data.assignments.map((row) => (row.permissionId === permissionId ? { ...row, [key]: checked } : row)),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles & permissions" />
            <div className="space-y-6">
                <AdminPageHeader title="Roles & permissions" description="Define who can view, create, edit, and delete each admin module." />

                <form onSubmit={createRole} className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end dark:border-slate-800 dark:bg-slate-950">
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="roleName">New role name</Label>
                        <Input id="roleName" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} placeholder="e.g. Content manager" />
                        <InputError message={createForm.errors.name} />
                    </div>
                    <Button type="submit" disabled={createForm.processing} className="bg-indigo-600 hover:bg-indigo-700">
                        Add role
                    </Button>
                </form>

                <div className="flex flex-wrap gap-2">
                    {roles.map((role) => (
                        <Button
                            key={role.id}
                            type="button"
                            variant={role.id === activeRole?.id ? 'default' : 'outline'}
                            className={role.id === activeRole?.id ? 'bg-indigo-600 hover:bg-indigo-700' : ''}
                            onClick={() => {
                                setActiveRoleId(role.id);
                                permissionForm.setData(
                                    'assignments',
                                    permissions.map((permission) => {
                                        const flags = matrix[role.id]?.[permission.id] ?? { read: false, create: false, update: false, delete: false };
                                        return { permissionId: permission.id, ...flags };
                                    }),
                                );
                            }}
                        >
                            {role.name}
                        </Button>
                    ))}
                </div>

                {activeRole ? (
                    <form onSubmit={savePermissions} className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                            <div>
                                <h2 className="font-semibold text-slate-900 dark:text-white">{activeRole.name}</h2>
                                <p className="text-xs text-slate-500">{activeRole.slug}</p>
                            </div>
                            {activeRole.slug !== 'super_admin' ? (
                                <ConfirmDelete
                                    title="Delete role?"
                                    description="Users must be reassigned before this role can be removed."
                                    onConfirm={() => router.delete(route('admin.roles.destroy', activeRole.id))}
                                />
                            ) : null}
                        </div>
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead className="bg-slate-50 dark:bg-slate-900/60">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Module / permission</th>
                                    {actions.map((action) => (
                                        <th key={action.key} className="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">
                                            {action.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {permissions.map((permission) => {
                                    const row = permissionForm.data.assignments.find((item) => item.permissionId === permission.id);
                                    const locked = activeRole.slug === 'super_admin';

                                    return (
                                        <tr key={permission.id}>
                                            <td className="px-4 py-3">
                                                <p className="text-sm font-medium text-slate-900 dark:text-slate-100">{permission.name}</p>
                                                <p className="text-xs text-slate-500">{permission.module}</p>
                                            </td>
                                            {actions.map((action) => (
                                                <td key={action.key} className="px-4 py-3 text-center">
                                                    <Checkbox
                                                        checked={row?.[action.key] ?? false}
                                                        disabled={locked}
                                                        onCheckedChange={(checked) => toggleFlag(permission.id, action.key, checked === true)}
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        <div className="border-t border-slate-200 px-4 py-3 dark:border-slate-800">
                            <Button type="submit" disabled={activeRole.slug === 'super_admin'} className="bg-indigo-600 hover:bg-indigo-700">
                                Save permissions
                            </Button>
                        </div>
                    </form>
                ) : null}
            </div>
        </AppLayout>
    );
}
