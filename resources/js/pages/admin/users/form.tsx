import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useMemo } from 'react';

type RoleOption = { id: number; name: string; slug: string };

type UserForm = {
    id: string;
    name: string | null;
    email: string | null;
    countryCode: string;
    mobileNumber: string;
    roleId: number | null;
};

type Props = {
    user: UserForm | null;
    roles: RoleOption[];
};

export default function AdminUserForm({ user, roles }: Props) {
    const editing = Boolean(user?.id);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Users', href: '/admin/users' },
        { title: editing ? 'Edit user' : 'Add user', href: '#' },
    ];

    const form = useForm({
        name: user?.name ?? '',
        roleId: user?.roleId ? String(user.roleId) : String(roles[0]?.id ?? ''),
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        countryCode: user?.countryCode ?? '+91',
        mobileNumber: user?.mobileNumber ?? '',
    });

    const { data, setData, post, put, processing, errors } = form;

    form.transform((payload) => ({
        ...payload,
        roleId: Number(payload.roleId),
    }));

    const selectedRole = useMemo(() => roles.find((role) => String(role.id) === data.roleId), [data.roleId, roles]);
    const isMarketplace = selectedRole?.slug === 'marketplace_user';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (editing && user?.id) {
            put(route('admin.users.update', user.id));
        } else {
            post(route('admin.users.store'));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit user' : 'Add user'} />
            <div className="mx-auto max-w-lg space-y-6">
                <AdminPageHeader
                    title={editing ? 'Edit user' : 'Add user'}
                    description="Assign a role, credentials for panel access, and mobile identity for the app."
                />
                <form onSubmit={submit} className="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Full name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required minLength={2} maxLength={40} />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Role</Label>
                        <Select value={data.roleId} onValueChange={(value) => setData('roleId', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select role" />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role.id} value={String(role.id)}>
                                        {role.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.roleId} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email {isMarketplace ? '(optional)' : '(required for panel login)'}</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required={!isMarketplace}
                            autoComplete="email"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">{editing ? 'New password (optional)' : 'Password'}</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            required={!editing && !isMarketplace}
                            autoComplete={editing ? 'new-password' : 'password'}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Confirm password</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            autoComplete="new-password"
                        />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2 sm:col-span-1">
                            <Label htmlFor="countryCode">Country {isMarketplace ? '' : '(optional)'}</Label>
                            <Input
                                id="countryCode"
                                value={data.countryCode}
                                onChange={(e) => setData('countryCode', e.target.value)}
                                required={isMarketplace}
                            />
                            <InputError message={errors.countryCode} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="mobileNumber">Mobile number {isMarketplace ? '(required)' : '(optional)'}</Label>
                            <Input
                                id="mobileNumber"
                                value={data.mobileNumber}
                                onChange={(e) => setData('mobileNumber', e.target.value)}
                                required={isMarketplace}
                            />
                            <InputError message={errors.mobileNumber} />
                        </div>
                    </div>

                    <div className="flex gap-3 pt-2">
                        <Button type="submit" disabled={processing} className="bg-indigo-600 hover:bg-indigo-700">
                            {editing ? 'Save changes' : 'Create user'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <a href={route('admin.users.index')}>Cancel</a>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
