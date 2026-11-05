import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaginationLinks } from '@/components/admin/pagination-links';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    FlaskConical,
    LoaderCircle,
    Percent,
    PlayCircle,
    Plus,
    ReceiptText,
    Save,
    ShieldCheck,
    Trash2,
    WalletCards,
} from 'lucide-react';
import { type FormEventHandler, type ReactNode, useEffect, useState } from 'react';

type SellerPaymentSettings = {
    sellerPayoutsEnabled: boolean;
    platformCommissionPercent: string;
    earningHoldDays: number;
    minimumWithdrawalRupees: string;
    buyerGstEnabled: boolean;
    buyerGstPercent: string;
    commissionGstEnabled: boolean;
    commissionGstPercent: string;
    tdsEnabled: boolean;
    tdsPercent: string;
    gstTcsEnabled: boolean;
    gstTcsPercent: string;
    settlementCycles: { startDay: number; endDay: number; settlementDay: number }[];
};

type Props = {
    settings: SellerPaymentSettings;
    paymentEnvironment: string;
    marketplaceTimezone: string;
    serverTime: string;
    earnings: PaginatedEarnings;
};

type SettlementEarning = {
    id: string;
    itemName: string;
    itemType: string;
    seller: { name: string; settlementMode: 'manual' | 'automatic' };
    buyerPaidPaise: number;
    commissionPaise: number;
    sellerNetPaise: number;
    commissionPercent: number;
    status: string;
    paymentEnvironment: string;
    createdAt: string | null;
    availableAt: string | null;
    cycle: {
        type: string;
        startDay: number | null;
        endDay: number | null;
        settlementDay: number | null;
        holdDays: number | null;
    };
    canProcessNow: boolean;
};

type PaginatedEarnings = {
    data: SettlementEarning[];
    links: { url: string | null; label: string; active: boolean }[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Seller payments', href: '/admin/settings/seller-payments' },
];

const inputClass = 'h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 dark:border-slate-700';

export default function SellerPaymentSettingsPage({ settings, paymentEnvironment, marketplaceTimezone, serverTime, earnings }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canUpdate = permissions.includes('seller_payments.update');
    const form = useForm({ ...settings });
    const { data, setData, put, processing, errors, isDirty } = form;
    const [currentTime, setCurrentTime] = useState(() => new Date(serverTime));
    const [processingEarning, setProcessingEarning] = useState<string | null>(null);

    useEffect(() => {
        const timer = window.setInterval(() => setCurrentTime((value) => new Date(value.getTime() + 1000)), 1000);
        return () => window.clearInterval(timer);
    }, []);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        if (!canUpdate) return;

        put(route('admin.settings.seller-payments.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Seller payments" />
            <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="Seller payments"
                    description="Manage marketplace commission, earning release rules, withdrawals and future tax configuration."
                />

                <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                    <FlaskConical className="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p className="font-semibold">{paymentEnvironment === 'live' ? 'Live mode' : 'Test mode — no real seller payout'}</p>
                        <p className="mt-1 text-sm opacity-80">
                            Payment mode is controlled by the secure server environment. Saved values apply to future transactions only; existing
                            earnings keep their original snapshot.
                        </p>
                        <p className="mt-2 text-xs font-medium">
                            Marketplace time: {formatDateTime(currentTime, marketplaceTimezone)} ({marketplaceTimezone})
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-5">
                    <AdminFormPanel
                        title="Marketplace split"
                        description="Set the platform share and the delay before a seller earning becomes withdrawable."
                        icon={Percent}
                    >
                        <div className="grid gap-5 sm:grid-cols-2">
                            <NumberField
                                id="platformCommissionPercent"
                                label="Platform commission"
                                value={data.platformCommissionPercent}
                                onChange={(value) => setData('platformCommissionPercent', value)}
                                suffix="%"
                                min="0"
                                max="100"
                                step="0.01"
                                error={errors.platformCommissionPercent}
                                disabled={!canUpdate}
                                hint={`Seller currently receives ${(100 - Number(data.platformCommissionPercent || 0)).toFixed(2)}%.`}
                            />
                            <NumberField
                                id="earningHoldDays"
                                label="Earning hold period"
                                value={data.earningHoldDays.toString()}
                                onChange={(value) => setData('earningHoldDays', Number(value))}
                                suffix="days"
                                min="0"
                                max="365"
                                step="1"
                                error={errors.earningHoldDays}
                                disabled={!canUpdate}
                                hint="Pending earnings become eligible after this period."
                            />
                        </div>
                    </AdminFormPanel>

                    <AdminFormPanel
                        title="Wallet settlement cycles"
                        description="Map each sale day to the day its seller earning becomes eligible. Cycles must cover days 1 through 31 without gaps."
                        icon={CalendarClock}
                    >
                        <div className="space-y-4">
                            {data.settlementCycles.length === 0 ? (
                                <div className="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700">
                                    No cycles configured. New earnings currently use the legacy hold period above.
                                </div>
                            ) : null}
                            {data.settlementCycles.map((cycle, index) => (
                                <div
                                    key={index}
                                    className="grid items-end gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-[1fr_1fr_1fr_auto] dark:border-slate-700"
                                >
                                    <DaySelect
                                        label="From day"
                                        value={cycle.startDay}
                                        disabled={!canUpdate}
                                        onChange={(value) => {
                                            const next = [...data.settlementCycles];
                                            next[index] = { ...cycle, startDay: value };
                                            setData('settlementCycles', next);
                                        }}
                                    />
                                    <DaySelect
                                        label="To day"
                                        value={cycle.endDay}
                                        disabled={!canUpdate}
                                        onChange={(value) => {
                                            const next = [...data.settlementCycles];
                                            next[index] = { ...cycle, endDay: value };
                                            setData('settlementCycles', next);
                                        }}
                                    />
                                    <DaySelect
                                        label="Settlement day"
                                        value={cycle.settlementDay}
                                        disabled={!canUpdate}
                                        onChange={(value) => {
                                            const next = [...data.settlementCycles];
                                            next[index] = { ...cycle, settlementDay: value };
                                            setData('settlementCycles', next);
                                        }}
                                    />
                                    {canUpdate ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="h-11 rounded-xl text-red-600"
                                            onClick={() =>
                                                setData(
                                                    'settlementCycles',
                                                    data.settlementCycles.filter((_, row) => row !== index),
                                                )
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                            <span className="sr-only">Remove cycle</span>
                                        </Button>
                                    ) : null}
                                </div>
                            ))}
                            <InputError message={(errors as Record<string, string>).settlementCycles} />
                            {canUpdate ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="rounded-xl"
                                    onClick={() => {
                                        const previousEnd = data.settlementCycles.at(-1)?.endDay ?? 0;
                                        const startDay = Math.min(previousEnd + 1, 31);
                                        setData('settlementCycles', [...data.settlementCycles, { startDay, endDay: 31, settlementDay: 1 }]);
                                    }}
                                >
                                    <Plus className="size-4" /> Add cycle
                                </Button>
                            ) : null}
                            <p className="text-xs text-slate-500">
                                If the settlement day is inside or before the cycle, payout is scheduled in the following month. Day 31 is
                                automatically clamped for shorter months.
                            </p>
                        </div>
                    </AdminFormPanel>

                    <AdminFormPanel
                        title="Withdrawals"
                        description="Control whether sellers can request withdrawals and the minimum request amount."
                        icon={WalletCards}
                    >
                        <div className="space-y-5">
                            <ToggleCard
                                id="sellerPayoutsEnabled"
                                checked={data.sellerPayoutsEnabled}
                                onCheckedChange={(checked) => setData('sellerPayoutsEnabled', checked)}
                                title="Allow seller withdrawal requests"
                                description="Keep disabled until the wallet and manual approval workflow is deployed and tested."
                                disabled={!canUpdate}
                            />
                            <NumberField
                                id="minimumWithdrawalRupees"
                                label="Minimum withdrawal"
                                value={data.minimumWithdrawalRupees}
                                onChange={(value) => setData('minimumWithdrawalRupees', value)}
                                prefix="₹"
                                min="1"
                                max="10000000"
                                step="0.01"
                                error={errors.minimumWithdrawalRupees}
                                disabled={!canUpdate}
                                hint="The backend will reject requests below this amount."
                            />
                        </div>
                    </AdminFormPanel>

                    <AdminFormPanel
                        title="Tax configuration"
                        description="These values are stored for future server-side calculations. Keep them disabled until your CA confirms the exact treatment."
                        icon={ReceiptText}
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TaxSetting
                                id="buyerGst"
                                title="Buyer GST"
                                checked={data.buyerGstEnabled}
                                onCheckedChange={(checked) => setData('buyerGstEnabled', checked)}
                                value={data.buyerGstPercent}
                                onValueChange={(value) => setData('buyerGstPercent', value)}
                                error={errors.buyerGstPercent}
                                disabled={!canUpdate}
                            />
                            <TaxSetting
                                id="commissionGst"
                                title="GST on commission"
                                checked={data.commissionGstEnabled}
                                onCheckedChange={(checked) => setData('commissionGstEnabled', checked)}
                                value={data.commissionGstPercent}
                                onValueChange={(value) => setData('commissionGstPercent', value)}
                                error={errors.commissionGstPercent}
                                disabled={!canUpdate}
                            />
                            <TaxSetting
                                id="tds"
                                title="Seller TDS"
                                checked={data.tdsEnabled}
                                onCheckedChange={(checked) => setData('tdsEnabled', checked)}
                                value={data.tdsPercent}
                                onValueChange={(value) => setData('tdsPercent', value)}
                                error={errors.tdsPercent}
                                disabled={!canUpdate}
                            />
                            <TaxSetting
                                id="gstTcs"
                                title="GST TCS"
                                checked={data.gstTcsEnabled}
                                onCheckedChange={(checked) => setData('gstTcsEnabled', checked)}
                                value={data.gstTcsPercent}
                                onValueChange={(value) => setData('gstTcsPercent', value)}
                                error={errors.gstTcsPercent}
                                disabled={!canUpdate}
                            />
                        </div>
                    </AdminFormPanel>

                    <div className="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                            <ShieldCheck className="size-5 text-indigo-500" />
                            {canUpdate ? 'Only future orders and earnings use changed values.' : 'You have read-only access.'}
                        </div>
                        {canUpdate ? (
                            <Button type="submit" disabled={processing || !isDirty} className="min-w-36 rounded-xl">
                                {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                                Save settings
                            </Button>
                        ) : null}
                    </div>
                </form>

                <AdminFormPanel
                    title="Settlement earnings"
                    description="Review the cycle and scheduled settlement time captured when each sale was paid. Existing earnings keep this snapshot even when cycles change."
                    icon={WalletCards}
                >
                    {earnings.data.length ? (
                        <div className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1050px] text-left text-sm">
                                    <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-800/70 dark:text-slate-400">
                                        <tr>
                                            <th className="px-4 py-3">Sale</th>
                                            <th className="px-4 py-3">Seller</th>
                                            <th className="px-4 py-3">Amounts</th>
                                            <th className="px-4 py-3">Applied cycle</th>
                                            <th className="px-4 py-3">Settlement time</th>
                                            <th className="px-4 py-3">Status</th>
                                            <th className="px-4 py-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                                        {earnings.data.map((earning) => (
                                            <tr key={earning.id} className="align-top text-slate-700 dark:text-slate-200">
                                                <td className="px-4 py-4">
                                                    <p className="font-semibold text-slate-900 dark:text-white">{earning.itemName}</p>
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        {earning.itemType} · {formatDateTime(earning.createdAt, marketplaceTimezone)}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <p className="font-medium">{earning.seller.name}</p>
                                                    <p className="mt-1 text-xs text-slate-500 capitalize">
                                                        {earning.seller.settlementMode} settlement
                                                    </p>
                                                </td>
                                                <td className="px-4 py-4 text-xs">
                                                    <p>
                                                        Buyer: <strong>{formatMoney(earning.buyerPaidPaise)}</strong>
                                                    </p>
                                                    <p className="mt-1 text-red-600">
                                                        Commission ({earning.commissionPercent}%): -{formatMoney(earning.commissionPaise)}
                                                    </p>
                                                    <p className="mt-1 font-semibold text-emerald-600">
                                                        Seller: {formatMoney(earning.sellerNetPaise)}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-4 text-xs">
                                                    <p className="font-medium">{cycleLabel(earning)}</p>
                                                    <p className="mt-1 text-slate-500">Snapshot saved at purchase</p>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <p className="font-medium">{formatDateTime(earning.availableAt, marketplaceTimezone)}</p>
                                                    <p className="mt-1 text-xs text-slate-500">{marketplaceTimezone}</p>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <StatusBadge status={earning.status} />
                                                    <p className="mt-2 text-xs text-slate-400 uppercase">{earning.paymentEnvironment}</p>
                                                </td>
                                                <td className="px-4 py-4 text-right">
                                                    {canUpdate && earning.canProcessNow ? (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="rounded-lg"
                                                            disabled={processingEarning !== null}
                                                            onClick={() => {
                                                                if (
                                                                    !window.confirm(
                                                                        'Process this test earning now? This bypasses its scheduled date.',
                                                                    )
                                                                )
                                                                    return;
                                                                setProcessingEarning(earning.id);
                                                                router.post(
                                                                    route('admin.settings.seller-payments.earnings.process-now', earning.id),
                                                                    {},
                                                                    { preserveScroll: true, onFinish: () => setProcessingEarning(null) },
                                                                );
                                                            }}
                                                        >
                                                            {processingEarning === earning.id ? (
                                                                <LoaderCircle className="size-4 animate-spin" />
                                                            ) : (
                                                                <PlayCircle className="size-4" />
                                                            )}
                                                            Process now
                                                        </Button>
                                                    ) : (
                                                        <span className="text-xs text-slate-400">No action</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <PaginationLinks paginator={earnings} />
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700">
                            No seller earnings have been recorded yet.
                        </div>
                    )}
                    {paymentEnvironment === 'test' ? (
                        <p className="mt-3 text-xs text-slate-500">
                            Process now is test-only. It safely moves a waiting earning through the same release and automatic payout services used by
                            the scheduler.
                        </p>
                    ) : null}
                </AdminFormPanel>
            </div>
        </AppLayout>
    );
}

function formatMoney(paise: number): string {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(paise / 100);
}

function formatDateTime(value: string | Date | null, timeZone: string): string {
    if (!value) return 'Not scheduled';
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return 'Not scheduled';

    return new Intl.DateTimeFormat('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'medium',
        timeZone,
    }).format(date);
}

function cycleLabel(earning: SettlementEarning): string {
    if (earning.cycle.type === 'monthly_cycle' && earning.cycle.startDay && earning.cycle.endDay && earning.cycle.settlementDay) {
        return `Sale days ${earning.cycle.startDay}–${earning.cycle.endDay} → day ${earning.cycle.settlementDay}`;
    }
    if (earning.cycle.holdDays !== null) return `Legacy hold: ${earning.cycle.holdDays} day(s)`;
    return 'Legacy settlement rule';
}

function StatusBadge({ status }: { status: string }) {
    const normalized = status.toLowerCase();
    const styles =
        normalized === 'pending'
            ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200'
            : normalized === 'available'
              ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
              : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200';
    const label = normalized === 'pending' ? 'Waiting settlement' : normalized === 'available' ? 'Released' : status;

    return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${styles}`}>{label}</span>;
}

function DaySelect({ label, value, onChange, disabled }: { label: string; value: number; onChange: (value: number) => void; disabled?: boolean }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select value={String(value)} onValueChange={(next) => onChange(Number(next))} disabled={disabled}>
                <SelectTrigger className={inputClass}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {Array.from({ length: 31 }, (_, index) => index + 1).map((day) => (
                        <SelectItem key={day} value={String(day)}>
                            {day}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

type NumberFieldProps = {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    hint?: string;
    prefix?: string;
    suffix?: string;
    min?: string;
    max?: string;
    step?: string;
    disabled?: boolean;
};

function NumberField({ id, label, value, onChange, error, hint, prefix, suffix, min, max, step, disabled }: NumberFieldProps) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex">
                {prefix ? <InputAffix>{prefix}</InputAffix> : null}
                <Input
                    id={id}
                    type="number"
                    inputMode="decimal"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    min={min}
                    max={max}
                    step={step}
                    disabled={disabled}
                    className={`${inputClass} ${prefix ? 'rounded-l-none' : ''} ${suffix ? 'rounded-r-none' : ''}`}
                />
                {suffix ? <InputAffix trailing>{suffix}</InputAffix> : null}
            </div>
            {hint ? <p className="text-xs text-slate-500 dark:text-slate-400">{hint}</p> : null}
            <InputError message={error} />
        </div>
    );
}

function InputAffix({ children, trailing = false }: { children: ReactNode; trailing?: boolean }) {
    return (
        <span
            className={`flex min-w-12 items-center justify-center border border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 ${trailing ? 'rounded-r-xl border-l-0' : 'rounded-l-xl border-r-0'}`}
        >
            {children}
        </span>
    );
}

type ToggleCardProps = {
    id: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    title: string;
    description: string;
    disabled?: boolean;
};

function ToggleCard({ id, checked, onCheckedChange, title, description, disabled }: ToggleCardProps) {
    return (
        <label htmlFor={id} className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <Checkbox id={id} checked={checked} onCheckedChange={(value) => onCheckedChange(value === true)} disabled={disabled} />
            <span>
                <span className="block text-sm font-semibold text-slate-900 dark:text-white">{title}</span>
                <span className="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">{description}</span>
            </span>
        </label>
    );
}

type TaxSettingProps = {
    id: string;
    title: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    value: string;
    onValueChange: (value: string) => void;
    error?: string;
    disabled?: boolean;
};

function TaxSetting({ id, title, checked, onCheckedChange, value, onValueChange, error, disabled }: TaxSettingProps) {
    return (
        <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <div className="flex items-center gap-3">
                <Checkbox id={`${id}Enabled`} checked={checked} onCheckedChange={(next) => onCheckedChange(next === true)} disabled={disabled} />
                <Label htmlFor={`${id}Enabled`} className="font-semibold">
                    {title}
                </Label>
            </div>
            <div className="mt-4 flex">
                <Input
                    id={`${id}Percent`}
                    type="number"
                    inputMode="decimal"
                    value={value}
                    onChange={(event) => onValueChange(event.target.value)}
                    min="0"
                    max="100"
                    step="0.01"
                    disabled={disabled || !checked}
                    className={`${inputClass} rounded-r-none`}
                />
                <InputAffix trailing>%</InputAffix>
            </div>
            <InputError message={error} />
        </div>
    );
}
