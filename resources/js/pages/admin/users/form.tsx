import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminPageHeader } from '@/components/admin/page-header';
import CountryCodeSelect from '@/components/country-code-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Cpu, LoaderCircle, LockKeyhole, Mail, ReceiptText, Save, ShieldCheck, Smartphone, UserRound } from 'lucide-react';
import { type FormEventHandler, useMemo } from 'react';

type RoleOption = { id: number; name: string; slug: string };
type MachineOption = { id: string; name: string; imageUri: string | null };

type UserForm = {
    id: string;
    name: string | null;
    email: string | null;
    countryCode: string;
    mobileNumber: string;
    roleId: number | null;
    roleSlug: string | null;
    status: 'active' | 'inactive';
    machineIds: string[];
    allowOtpLogin: boolean;
    allowPasswordLogin: boolean;
    hasPassword: boolean;
    sellerSettlementMode: 'manual' | 'automatic';
    taxStateCode: string | null;
    gstRegistered: boolean;
    gstin: string | null;
};

type Props = {
    user: UserForm | null;
    roles: RoleOption[];
    machines: MachineOption[];
    indiaStates: Record<string, string>;
};

export default function AdminUserForm({ user, roles, machines, indiaStates }: Props) {
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
        machineIds: user?.machineIds ?? [],
        allowOtpLogin: user?.allowOtpLogin ?? roles[0]?.slug === 'marketplace_user',
        allowPasswordLogin: user?.allowPasswordLogin ?? roles[0]?.slug !== 'marketplace_user',
        sellerSettlementMode: user?.sellerSettlementMode ?? 'manual',
        taxStateCode: user?.taxStateCode ?? '',
        gstRegistered: user?.gstRegistered ?? false,
        gstin: user?.gstin ?? '',
    });

    const { data, setData, post, put, processing, errors } = form;

    form.transform((payload) => ({ ...payload, roleId: Number(payload.roleId) }));

    const selectedRole = useMemo(() => roles.find((role) => String(role.id) === data.roleId), [data.roleId, roles]);
    const isMarketplace = selectedRole?.slug === 'marketplace_user';
    const allMachinesSelected = machines.length > 0 && data.machineIds.length === machines.length;

    const toggleMachine = (machineId: string, selected: boolean) => {
        setData('machineIds', selected ? [...new Set([...data.machineIds, machineId])] : data.machineIds.filter((id) => id !== machineId));
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        if (editing && user?.id) {
            put(route('admin.users.update', user.id));
        } else {
            post(route('admin.users.store'));
        }
    };

    const fieldClass = 'h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20 dark:border-slate-700';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit user' : 'Add user'} />
            <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
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
                                {isMarketplace ? (
                                    <div className="grid gap-2">
                                        <Label>Seller payout method</Label>
                                        <Select
                                            value={data.sellerSettlementMode}
                                            onValueChange={(value: 'manual' | 'automatic') => setData('sellerSettlementMode', value)}
                                        >
                                            <SelectTrigger className={fieldClass}><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="manual">Wallet + manual withdrawal</SelectItem>
                                                <SelectItem value="automatic">Automatic cycle settlement</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p className="text-xs text-slate-500">
                                            Automatic sellers cannot submit withdrawal requests; eligible earnings are settled on the configured date.
                                        </p>
                                        <InputError message={errors.sellerSettlementMode} />
                                    </div>
                                ) : null}
                            </div>
                        </AdminFormPanel>
                    </div>

                    {isMarketplace ? (
                        <AdminFormPanel
                            title="India GST profile"
                            description="The seller state determines CGST/SGST or IGST. GST is charged on this seller's listings only when the seller is marked GST registered."
                            icon={ReceiptText}
                        >
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>GST state</Label>
                                    <Select value={data.taxStateCode || undefined} onValueChange={(value) => setData('taxStateCode', value)}>
                                        <SelectTrigger className={fieldClass}><SelectValue placeholder="Select state" /></SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(indiaStates).map(([code, name]) => (
                                                <SelectItem key={code} value={code}>{code} · {name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.taxStateCode} />
                                </div>
                                <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                    <Checkbox checked={data.gstRegistered} onCheckedChange={(value) => setData('gstRegistered', value === true)} />
                                    <span>
                                        <span className="block text-sm font-semibold">GST-registered seller</span>
                                        <span className="mt-1 block text-xs text-slate-500">Charge buyer GST on this seller's taxable listings.</span>
                                    </span>
                                </label>
                                {data.gstRegistered ? (
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="gstin">GSTIN</Label>
                                        <Input
                                            id="gstin"
                                            value={data.gstin}
                                            onChange={(event) => setData('gstin', event.target.value.toUpperCase())}
                                            maxLength={15}
                                            placeholder="24ABCDE1234F1Z5"
                                            className={fieldClass}
                                            required
                                        />
                                        <InputError message={errors.gstin} />
                                    </div>
                                ) : null}
                            </div>
                        </AdminFormPanel>
                    ) : null}

                    <AdminFormPanel
                        title="Login methods"
                        description="Choose how this user can sign in. At least one method must remain enabled."
                        icon={LockKeyhole}
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <Checkbox
                                    checked={data.allowOtpLogin}
                                    disabled={data.allowOtpLogin && !data.allowPasswordLogin}
                                    onCheckedChange={(value) => setData('allowOtpLogin', value === true)}
                                />
                                <span>
                                    <span className="block text-sm font-semibold text-slate-900 dark:text-white">Allow OTP login</span>
                                    <span className="mt-1 block text-xs text-slate-500">Use the verified mobile number and a one-time code.</span>
                                </span>
                            </label>
                            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <Checkbox
                                    checked={data.allowPasswordLogin}
                                    disabled={data.allowPasswordLogin && !data.allowOtpLogin}
                                    onCheckedChange={(value) => setData('allowPasswordLogin', value === true)}
                                />
                                <span>
                                    <span className="block text-sm font-semibold text-slate-900 dark:text-white">Allow password login</span>
                                    <span className="mt-1 block text-xs text-slate-500">Use an email or mobile number together with a password.</span>
                                </span>
                            </label>
                        </div>
                        {!data.allowOtpLogin && !data.allowPasswordLogin ? (
                            <p className="mt-3 text-sm font-medium text-red-600">Enable at least one login method.</p>
                        ) : null}
                        <InputError message={errors.allowOtpLogin} />
                        <InputError message={errors.allowPasswordLogin} />
                    </AdminFormPanel>

                    <div className="grid gap-5 lg:grid-cols-2">
                        <AdminFormPanel
                            title="Panel credentials"
                            description="Used when password login is enabled. Email is optional when a mobile number is available."
                            icon={LockKeyhole}
                        >
                            <div className="space-y-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email (optional)</Label>
                                    <div className="relative">
                                        <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(event) => setData('email', event.target.value)}
                                            autoComplete="email"
                                            className={`${fieldClass} pl-10`}
                                        />
                                    </div>
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            {editing ? 'New password' : 'Password'} {data.allowPasswordLogin ? '(required)' : '(optional)'}
                                        </Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            value={data.password}
                                            onChange={(event) => setData('password', event.target.value)}
                                            required={data.allowPasswordLogin && (!editing || !user?.hasPassword)}
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
                                    <CountryCodeSelect
                                        value={data.countryCode}
                                        onValueChange={(value) => setData('countryCode', value)}
                                        className={fieldClass}
                                    />
                                    <InputError message={errors.countryCode} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="mobileNumber">
                                        Mobile number {data.allowOtpLogin || isMarketplace ? '(required)' : '(optional)'}
                                    </Label>
                                    <Input
                                        id="mobileNumber"
                                        value={data.mobileNumber}
                                        onChange={(event) => setData('mobileNumber', event.target.value)}
                                        required={data.allowOtpLogin || isMarketplace}
                                        className={fieldClass}
                                    />
                                    <InputError message={errors.mobileNumber} />
                                </div>
                            </div>
                        </AdminFormPanel>
                    </div>

                    <AdminFormPanel
                        title="Machine access"
                        description="Choose which machine catalogs and designs are available to this user."
                        icon={Cpu}
                    >
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 dark:border-indigo-900/50 dark:bg-indigo-950/30">
                            <div>
                                <p className="text-sm font-semibold text-indigo-900 dark:text-indigo-100">
                                    {data.machineIds.length} of {machines.length} machines selected
                                </p>
                                <p className="mt-1 text-xs text-indigo-700 dark:text-indigo-300">
                                    {isMarketplace
                                        ? 'Select at least one machine for marketplace users.'
                                        : 'For panel users, no selection keeps access to all machines.'}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                className="rounded-xl"
                                onClick={() => setData('machineIds', allMachinesSelected ? [] : machines.map((machine) => machine.id))}
                                disabled={machines.length === 0}
                            >
                                {allMachinesSelected ? 'Clear all' : 'Select all'}
                            </Button>
                        </div>

                        {machines.length ? (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {machines.map((machine) => {
                                    const selected = data.machineIds.includes(machine.id);

                                    return (
                                        <label
                                            key={machine.id}
                                            className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-colors ${
                                                selected
                                                    ? 'border-indigo-300 bg-indigo-50 dark:border-indigo-700 dark:bg-indigo-950/30'
                                                    : 'border-slate-200 hover:border-indigo-200 dark:border-slate-700'
                                            }`}
                                        >
                                            <Checkbox checked={selected} onCheckedChange={(value) => toggleMachine(machine.id, value === true)} />
                                            {machine.imageUri ? (
                                                <img src={machine.imageUri} alt="" className="size-10 rounded-lg object-cover" />
                                            ) : (
                                                <span className="flex size-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400 dark:bg-slate-800">
                                                    <Cpu className="size-4" />
                                                </span>
                                            )}
                                            <span className="min-w-0 truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                                                {machine.name}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500 dark:border-slate-700">
                                No active machines are available.
                            </p>
                        )}
                        <InputError message={errors.machineIds} />
                    </AdminFormPanel>

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
