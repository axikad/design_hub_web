import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck, CircleDollarSign, Clock3, History, Landmark, ListChecks, XCircle } from 'lucide-react';
import { type FormEvent } from 'react';

type StatusHistory = {
    id: number;
    fromStatus: string | null;
    toStatus: string;
    note: string | null;
    actor: { id: string; name: string | null } | null;
    createdAt: string;
};

type LedgerEntry = {
    id: string;
    entryType: string;
    balanceBucket: string;
    amountPaise: number;
    createdAt: string;
};

type Withdrawal = {
    id: string;
    seller: { id: string; name: string | null; countryCode: string; mobileNumber: string; email: string | null } | null;
    amountPaise: number;
    feePaise: number;
    netTransferPaise: number;
    currency: string;
    status: string;
    paymentEnvironment: 'test' | 'live';
    payoutMode: string;
    requestedAt: string | null;
    approvedAt: string | null;
    processedAt: string | null;
    cancelledAt: string | null;
    approvedBy: { id: string; name: string | null } | null;
    rejectionReason: string | null;
    provider: string | null;
    providerTransferId: string | null;
    providerStatus: string | null;
    balances: { pendingPaise: number; availablePaise: number; reservedPaise: number; paidPaise: number };
    history: StatusHistory[];
    ledgerEntries: LedgerEntry[];
    actions: { canApprove: boolean; canReject: boolean; canCompleteSimulated: boolean };
};

const money = (paise: number, currency = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency }).format(paise / 100);
const label = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

function statusTone(status: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
    if (status === 'paid') return 'success';
    if (status === 'requested' || status === 'processing') return 'warning';
    if (status === 'approved') return 'info';
    if (status === 'rejected' || status === 'failed') return 'danger';
    return 'neutral';
}

function ActionForm({
    title,
    description,
    endpoint,
    buttonLabel,
    buttonVariant = 'default',
}: {
    title: string;
    description: string;
    endpoint: string;
    buttonLabel: string;
    buttonVariant?: 'default' | 'destructive';
}) {
    const { data, setData, post, processing, errors, reset } = useForm({ note: '' });
    const withdrawalError = (errors as Record<string, string | undefined>).withdrawal;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(endpoint, { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <h3 className="text-sm font-bold text-slate-900 dark:text-white">{title}</h3>
            <p className="mt-1 text-xs leading-5 text-slate-500">{description}</p>
            <label className="mt-3 block text-xs font-semibold text-slate-700 dark:text-slate-300" htmlFor={`note-${buttonLabel}`}>
                Audit note
            </label>
            <textarea
                id={`note-${buttonLabel}`}
                value={data.note}
                onChange={(event) => setData('note', event.target.value)}
                rows={3}
                required
                minLength={3}
                maxLength={1000}
                className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-950"
                placeholder="Record why this action is being taken..."
            />
            {errors.note ? <p className="mt-1 text-xs text-rose-600">{errors.note}</p> : null}
            {withdrawalError ? <p className="mt-1 text-xs text-rose-600">{withdrawalError}</p> : null}
            <Button type="submit" variant={buttonVariant} disabled={processing} className="mt-3 rounded-xl">
                {buttonLabel}
            </Button>
        </form>
    );
}

export default function WithdrawalShow({ withdrawal }: { withdrawal: Withdrawal }) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canManage = permissions.includes('withdrawals.manage');
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Withdrawals', href: '/admin/withdrawals' },
        { title: withdrawal.id, href: route('admin.withdrawals.show', withdrawal.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Withdrawal ${withdrawal.id}`} />
            <div className="mx-auto max-w-5xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="Withdrawal review"
                    description={withdrawal.id}
                    actions={
                        <Button asChild variant="outline" className="rounded-xl">
                            <Link href={route('admin.withdrawals.index')}>
                                <ArrowLeft className="size-4" /> Back
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    <AdminFormPanel title="Request details" description="Seller, amount and payout state." icon={Landmark}>
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <div className="col-span-2">
                                <dt className="text-xs text-slate-500">Seller</dt>
                                <dd className="mt-1 font-semibold text-slate-900 dark:text-white">{withdrawal.seller?.name || 'Deleted seller'}</dd>
                                <dd className="text-xs text-slate-500">
                                    {withdrawal.seller ? `${withdrawal.seller.countryCode}${withdrawal.seller.mobileNumber}` : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">Requested</dt>
                                <dd className="mt-1 font-bold">{money(withdrawal.amountPaise, withdrawal.currency)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">Net transfer</dt>
                                <dd className="mt-1 font-bold">{money(withdrawal.netTransferPaise, withdrawal.currency)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">Status</dt>
                                <dd className="mt-1">
                                    <AdminStatusBadge tone={statusTone(withdrawal.status)}>{withdrawal.status}</AdminStatusBadge>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">Environment</dt>
                                <dd className="mt-1">
                                    <AdminStatusBadge tone={withdrawal.paymentEnvironment === 'test' ? 'warning' : 'success'}>
                                        {withdrawal.paymentEnvironment}
                                    </AdminStatusBadge>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">Payout mode</dt>
                                <dd className="mt-1 font-medium">{label(withdrawal.payoutMode)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">Requested at</dt>
                                <dd className="mt-1 font-medium">
                                    {withdrawal.requestedAt ? new Date(withdrawal.requestedAt).toLocaleString() : '—'}
                                </dd>
                            </div>
                        </dl>
                    </AdminFormPanel>

                    <AdminFormPanel
                        title="Seller balances"
                        description={`Balances for the ${withdrawal.paymentEnvironment} environment.`}
                        icon={CircleDollarSign}
                    >
                        <div className="grid grid-cols-2 gap-3">
                            {Object.entries(withdrawal.balances).map(([key, value]) => (
                                <div key={key} className="rounded-xl bg-slate-50 p-4 dark:bg-slate-950/60">
                                    <p className="text-xs text-slate-500">{label(key.replace('Paise', ''))}</p>
                                    <p className="mt-1 text-lg font-bold text-slate-900 dark:text-white">{money(value, withdrawal.currency)}</p>
                                </div>
                            ))}
                        </div>
                    </AdminFormPanel>
                </div>

                {canManage && (withdrawal.actions.canApprove || withdrawal.actions.canReject || withdrawal.actions.canCompleteSimulated) ? (
                    <AdminFormPanel
                        title="Withdrawal actions"
                        description="Every action requires an audit note and is recorded permanently."
                        icon={ListChecks}
                    >
                        <div className="grid gap-4 lg:grid-cols-2">
                            {withdrawal.actions.canApprove ? (
                                <ActionForm
                                    title="Approve request"
                                    description="Validate the request. The amount stays reserved until payout completion."
                                    endpoint={route('admin.withdrawals.approve', withdrawal.id)}
                                    buttonLabel="Approve withdrawal"
                                />
                            ) : null}
                            {withdrawal.actions.canCompleteSimulated ? (
                                <ActionForm
                                    title="Complete simulated payout"
                                    description="Test mode only. Moves the reserved amount into the paid bucket."
                                    endpoint={route('admin.withdrawals.complete-simulated', withdrawal.id)}
                                    buttonLabel="Mark simulated payout paid"
                                />
                            ) : null}
                            {withdrawal.actions.canReject ? (
                                <ActionForm
                                    title="Reject request"
                                    description="Returns the reserved amount to the seller's available balance."
                                    endpoint={route('admin.withdrawals.reject', withdrawal.id)}
                                    buttonLabel="Reject withdrawal"
                                    buttonVariant="destructive"
                                />
                            ) : null}
                        </div>
                    </AdminFormPanel>
                ) : null}

                <div className="grid gap-6 lg:grid-cols-2">
                    <AdminFormPanel title="Status history" description="Actor, note and timestamp for each transition." icon={History}>
                        <div className="space-y-4">
                            {withdrawal.history.map((entry) => (
                                <div
                                    key={entry.id}
                                    className="flex gap-3 border-b border-slate-100 pb-4 last:border-0 last:pb-0 dark:border-slate-800"
                                >
                                    <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50">
                                        {entry.toStatus === 'rejected' ? <XCircle className="size-4" /> : <BadgeCheck className="size-4" />}
                                    </span>
                                    <div>
                                        <p className="text-sm font-semibold">
                                            {entry.fromStatus ? `${label(entry.fromStatus)} → ` : ''}
                                            {label(entry.toStatus)}
                                        </p>
                                        <p className="mt-1 text-xs leading-5 text-slate-500">{entry.note || 'No note'}</p>
                                        <p className="mt-1 text-[11px] text-slate-400">
                                            {entry.actor?.name || 'System'} · {new Date(entry.createdAt).toLocaleString()}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </AdminFormPanel>

                    <AdminFormPanel title="Ledger audit" description="Immutable money movements linked to this withdrawal." icon={Clock3}>
                        <div className="space-y-3">
                            {withdrawal.ledgerEntries.map((entry) => (
                                <div
                                    key={entry.id}
                                    className="flex items-center justify-between rounded-xl border border-slate-100 p-3 dark:border-slate-800"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-xs font-semibold text-slate-800 dark:text-slate-100">{label(entry.entryType)}</p>
                                        <p className="mt-1 text-[11px] text-slate-400">
                                            {label(entry.balanceBucket)} · {new Date(entry.createdAt).toLocaleString()}
                                        </p>
                                    </div>
                                    <p className={`ml-3 text-sm font-bold ${entry.amountPaise >= 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
                                        {entry.amountPaise >= 0 ? '+' : '−'}
                                        {money(Math.abs(entry.amountPaise), withdrawal.currency)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </AdminFormPanel>
                </div>
            </div>
        </AppLayout>
    );
}
