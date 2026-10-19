import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, LockKeyhole, Mail, Save, ShieldCheck, Smartphone, UserRound } from 'lucide-react';
import { type FormEventHandler, useMemo } from 'react';

type RoleOption = { id: number; name: string; slug: string };

type UserForm = {
    id: string;
    name: string | null;
    email: string | null;
    countryCode: string;
    mobileNumber: string;
    roleId: number | null;
    roleSlug: string | null;
    status: 'active' | 'inactive';
};

type Props = {
    user: UserForm | null;
    roles: RoleOption[];
};

export default function AdminUserForm({ user, roles }: Props) {
    const editing = Boolean(user?.id);
    const protectedSuperAdmin = user?.roleSlug === 'super_admin';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Users', href: '/admin/users' },
        { title: editing ? 'Edit user' : 'Add user', href: '#' },
    ];

    const form = useForm({
        name: user?.name ?? '',
        roleId: user?.roleId ? String(user.roleId) : String(roles[0]?.id ?? ''),
        status: user?.status ?? 'active',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        countryCode: user?.countryCode ?? '+91',
        mobileNumber: user?.mobileNumber ?? '',
    });

    const { data, setData, post, put, processing, errors } = form;

    form.transform((payload) => ({ ...payload, roleId: Number(payload.roleId) }));

    const selectedRole = useMemo(() => roles.find((role) => String(role.id) === data.roleId), [data.roleId, roles]);
    const isMarketplace = selectedRole?.slug === 'marketplace_user';

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        editing && user?.id ? put(route('admin.users.update', user.id)) : post(route('admin.users.store'));
    };

    const fieldClass = 'h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20 dark:border-slate-700';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit user' : 'Add user'} />
            <div className="mx-auto max-w-5xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title={editing ? 'Edit user' : 'Add user'}
                    description="Configure identity, role, panel credentials and mobile-app contact information."
                    actions={
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.users.index')}>
                                <ArrowLeft className="size-4" />
                                Back to users
                            </Link>
                        </Button>
                    }
                />

                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-5 lg:grid-cols-2">
                        <AdminFormPanel title="Account identity" description="The user's display name and account availability." icon={UserRound}>
                            <div className="space-y-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Full name</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(event) => setData('name', event.target.value)}
                                        required
                                        minLength={2}
                                        maxLength={40}
                                        className={fieldClass}
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Account status</Label>
                                    <Select
                                        value={data.status}
                                        onValueChange={(value: 'active' | 'inactive') => setData('status', value)}
                                        disabled={protectedSuperAdmin}
                                    >
                                        <SelectTrigger className={fieldClass}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="inactive">Inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                    {protectedSuperAdmin ? <p className="text-xs text-amber-600">Super Admin must remain active.</p> : null}
                                </div>
                            </div>
                        </AdminFormPanel>

                        <AdminFormPanel
                            title="Role and access"
                            description="Choose what this user can access in the admin panel or mobile app."
                            icon={ShieldCheck}
                        >
                            <div className="space-y-5">
                                <div className="grid gap-2">
                                    <Label>Role</Label>
                                    <Select value={data.roleId} onValueChange={(value) => setData('roleId', value)} disabled={protectedSuperAdmin}>
                                        <SelectTrigger className={fieldClass}>
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
                                    {protectedSuperAdmin ? <p className="text-xs text-amber-600">The Super Admin role is protected.</p> : null}
                                </div>
                                <div className="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-indigo-800 dark:border-indigo-900/50 dark:bg-indigo-950/30 dark:text-indigo-200">
                                    {isMarketplace
                                        ? 'Marketplace users sign in to the mobile app using their phone number.'
                                        : 'Panel users sign in to the admin workspace using email and password.'}
                                </div>
                            </div>
                        </AdminFormPanel>
                    </div>

                    <div className="grid gap-5 lg:grid-cols-2">
                        <AdminFormPanel
                            title="Panel credentials"
                            description="Required for users who can sign in to the admin panel."
                            icon={LockKeyhole}
                        >
                            <div className="space-y-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email {isMarketplace ? '(optional)' : '(required)'}</Label>
                                    <div className="relative">
                                        <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(event) => setData('email', event.target.value)}
                                            required={!isMarketplace}
                                            autoComplete="email"
                                            className={`${fieldClass} pl-10`}
                                        />
                                    </div>
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">{editing ? 'New password' : 'Password'}</Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            value={data.password}
                                            onChange={(event) => setData('password', event.target.value)}
                                            required={!editing && !isMarketplace}
                                            autoComplete="new-password"
                                            placeholder={editing ? 'Leave blank to keep' : ''}
                                            className={fieldClass}
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">Confirm password</Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            value={data.password_confirmation}
                                            onChange={(event) => setData('password_confirmation', event.target.value)}
                                            autoComplete="new-password"
                                            className={fieldClass}
                                        />
                                    </div>
                                </div>
                            </div>
                        </AdminFormPanel>

                        <AdminFormPanel
                            title="Mobile identity"
                            description="Phone details used by marketplace users and OTP authentication."
                            icon={Smartphone}
                        >
                            <div className="grid gap-4 sm:grid-cols-[120px_1fr]">
                                <div className="grid gap-2">
                                    <Label htmlFor="countryCode">Country code</Label>
                                    <Input
                                        id="countryCode"
                                        value={data.countryCode}
                                        onChange={(event) => setData('countryCode', event.target.value)}
                                        required={isMarketplace}
                                        className={fieldClass}
                                    />
                                    <InputError message={errors.countryCode} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="mobileNumber">Mobile number {isMarketplace ? '(required)' : '(optional)'}</Label>
                                    <Input
                                        id="mobileNumber"
                                        value={data.mobileNumber}
                                        onChange={(event) => setData('mobileNumber', event.target.value)}
                                        required={isMarketplace}
                                        className={fieldClass}
                                    />
                                    <InputError message={errors.mobileNumber} />
                                </div>
                            </div>
                        </AdminFormPanel>
                    </div>

                    <div className="sticky bottom-4 flex flex-wrap items-center justify-end gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
                        <Button type="button" variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.users.index')}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="rounded-xl bg-indigo-600 px-5 font-semibold hover:bg-indigo-700">
                            {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {processing ? 'Saving...' : editing ? 'Save changes' : 'Create user'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
