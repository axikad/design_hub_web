import { AdminEmptyState } from '@/components/admin/admin-empty-state';
import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDownToLine, Clock3, IndianRupee, Landmark, ListRestart, WalletCards } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type PaginationLink = { url: string | null; label: string; active: boolean };
type Paginator<T> = { data: T[]; links: PaginationLink[] };
type Wallet = {
    paymentEnvironment: 'test' | 'live';
    isTestMode: boolean;
    payoutsEnabled: boolean;
    settlementMode: 'manual' | 'automatic';
    manualWithdrawalsEnabled: boolean;
    balances: {
        pendingPaise: number;
        waitingSettlementPaise: number;
        availablePaise: number;
        reservedPaise: number;
        paidPaise: number;
    };
    minimumWithdrawalPaise: number;
    hasOpenWithdrawal: boolean;
    canWithdraw: boolean;
    nextReleaseAt: string | null;
    nextSettlementAt: string | null;
};
type Earning = {
    id: string;
    itemName: string;
    itemType: string;
    buyerPaidPaise: number;
    commissionPaise: number;
    sellerNetPaise: number;
    status: string;
    availableAt: string | null;
    createdAt: string | null;
};
type Withdrawal = {
    id: string;
    amountPaise: number;
    netTransferPaise: number;
    status: string;
    payoutMode: string;
    requestSource: string;
    canCancel: boolean;
    requestedAt: string | null;
};
type Transaction = {
    id: string;
    type: string;
    balanceBucket: string;
    amountPaise: number;
    createdAt: string | null;
};
type Props = {
    wallet: Wallet;
    earnings: Paginator<Earning>;
    withdrawals: Paginator<Withdrawal>;
    transactions: Paginator<Transaction>;
    activeTab: Tab;
};
type Tab = 'withdrawals' | 'earnings' | 'ledger';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'My Wallet', href: '/admin/wallet' },
];

const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
const formatMoney = (paise: number) => money.format(paise / 100);
const formatDate = (value: string | null) =>
    value ? new Intl.DateTimeFormat('en-IN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Not available';

function statusTone(status: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
    if (['paid', 'available'].includes(status)) return 'success';
    if (['requested', 'approved', 'processing', 'pending', 'held'].includes(status)) return 'warning';
    if (['rejected', 'failed', 'reversed', 'refunded'].includes(status)) return 'danger';
    if (status === 'cancelled') return 'neutral';
    return 'info';
}

function HistoryCard({ children }: { children: React.ReactNode }) {
    return (
        <div className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
            {children}
        </div>
    );
}

export default function SellerWalletIndex({ wallet, earnings, withdrawals, transactions, activeTab }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canRequest = permissions.includes('withdrawals.create_own');
    const canCancel = permissions.includes('withdrawals.cancel_own');
    const [tab, setTab] = useState<Tab>(activeTab);
    const form = useForm({ amountRupees: (wallet.minimumWithdrawalPaise / 100).toFixed(2) });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(route('admin.wallet.withdrawals.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const requestErrors = form.errors as Record<string, string>;
    const requestError = requestErrors.amountRupees || requestErrors.amountPaise || requestErrors.withdrawal;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Wallet" />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader title="My Wallet" description="Track your marketplace earnings, balance, and withdrawal requests." />

                <section className="overflow-hidden rounded-3xl bg-gradient-to-br from-sky-200 via-sky-100 to-indigo-100 p-6 text-slate-900 shadow-sm ring-1 ring-sky-200 md:p-8 dark:from-sky-900 dark:via-slate-900 dark:to-indigo-950 dark:text-white dark:ring-sky-900">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <span className="flex size-12 items-center justify-center rounded-2xl bg-white/50 dark:bg-white/10">
                            <WalletCards className="size-6" />
                        </span>
                        <AdminStatusBadge tone={wallet.isTestMode ? 'warning' : 'success'} dot={false}>
                            {wallet.isTestMode ? 'TEST MODE' : 'LIVE MODE'}
                        </AdminStatusBadge>
                    </div>
                    <p className="mt-7 text-sm font-medium text-slate-600 dark:text-slate-300">Available balance</p>
                    <p className="mt-1 text-4xl font-black tracking-tight md:text-5xl">{formatMoney(wallet.balances.availablePaise)}</p>
                    <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        {wallet.isTestMode ? 'Test mode — no real money will be transferred.' : 'Live balance available for payout.'}
                    </p>
                    <div className="mt-7 grid gap-3 border-t border-slate-500/20 pt-5 sm:grid-cols-4">
                        {[
                            ['Waiting settlement', wallet.balances.waitingSettlementPaise],
                            ['Reserved', wallet.balances.reservedPaise],
                            ['Paid', wallet.balances.paidPaise],
                            ['Next settlement', wallet.nextSettlementAt ? formatDate(wallet.nextSettlementAt) : 'None'],
                        ].map(([label, value]) => (
                            <div key={label as string}>
                                <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">{label}</p>
                                <p className="mt-1 text-sm font-bold">{typeof value === 'number' ? formatMoney(value) : value}</p>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-start gap-3">
                        <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300">
                            <ArrowDownToLine className="size-5" />
                        </span>
                        <div>
                            <h2 className="font-bold text-slate-900 dark:text-white">Withdraw funds</h2>
                            <p className="mt-1 text-sm text-slate-500">Minimum withdrawal {formatMoney(wallet.minimumWithdrawalPaise)}</p>
                        </div>
                    </div>

                    {!wallet.manualWithdrawalsEnabled ? (
                        <p className="mt-5 rounded-xl bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                            Automatic settlement is enabled. Eligible earnings are paid on their configured settlement date; no manual request is needed.
                        </p>
                    ) : !wallet.payoutsEnabled ? (
                        <p className="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500 dark:bg-slate-950">
                            Withdrawals are currently unavailable.
                        </p>
                    ) : wallet.hasOpenWithdrawal ? (
                        <p className="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                            You already have an open withdrawal request. Complete or cancel it before creating another one.
                        </p>
                    ) : canRequest ? (
                        <form onSubmit={submit} className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-start">
                            <div className="flex-1">
                                <div className="relative">
                                    <IndianRupee className="absolute top-3 left-3 size-4 text-slate-400" />
                                    <Input
                                        type="number"
                                        min={wallet.minimumWithdrawalPaise / 100}
                                        max={wallet.balances.availablePaise / 100}
                                        step="0.01"
                                        value={form.data.amountRupees}
                                        onChange={(event) => form.setData('amountRupees', event.target.value)}
                                        className="h-11 rounded-xl pl-9"
                                        aria-label="Withdrawal amount in rupees"
                                    />
                                </div>
                                {requestError ? <p className="mt-1 text-sm text-red-600">{requestError}</p> : null}
                            </div>
                            <Button
                                type="submit"
                                disabled={!wallet.canWithdraw || form.processing}
                                className="h-11 rounded-xl bg-indigo-600 px-6 hover:bg-indigo-700"
                            >
                                {form.processing ? 'Requesting...' : 'Request withdrawal'}
                            </Button>
                        </form>
                    ) : null}
                </section>

                <section>
                    <div className="mb-4 grid grid-cols-3 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                        {(
                            [
                                ['withdrawals', 'Payouts'],
                                ['earnings', 'Earnings'],
                                ['ledger', 'Ledger'],
                            ] as const
                        ).map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setTab(value)}
                                className={`rounded-lg px-3 py-2 text-sm font-semibold transition ${tab === value ? 'bg-white text-indigo-700 shadow-sm dark:bg-slate-950 dark:text-indigo-300' : 'text-slate-500'}`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    {tab === 'withdrawals' ? (
                        withdrawals.data.length ? (
                            <>
                                <HistoryCard>
                                    {withdrawals.data.map((withdrawal) => (
                                        <div key={withdrawal.id} className="flex flex-wrap items-center justify-between gap-4 p-4">
                                            <div>
                                                <p className="font-semibold text-slate-900 dark:text-white">
                                                    {withdrawal.requestSource === 'automatic' ? 'Automatic settlement' : 'Withdrawal request'}
                                                </p>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {formatDate(withdrawal.requestedAt)} · {withdrawal.payoutMode}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <div className="text-right">
                                                    <p className="font-bold text-slate-900 dark:text-white">{formatMoney(withdrawal.amountPaise)}</p>
                                                    <AdminStatusBadge tone={statusTone(withdrawal.status)}>{withdrawal.status}</AdminStatusBadge>
                                                </div>
                                                {canCancel && withdrawal.canCancel ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="rounded-lg"
                                                        onClick={() =>
                                                            router.post(
                                                                route('admin.wallet.withdrawals.cancel', withdrawal.id),
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        Cancel
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </div>
                                    ))}
                                </HistoryCard>
                                <PaginationLinks paginator={withdrawals} />
                            </>
                        ) : (
                            <AdminEmptyState icon={Landmark} title="No withdrawals yet" description="Your withdrawal requests will appear here." />
                        )
                    ) : null}

                    {tab === 'earnings' ? (
                        earnings.data.length ? (
                            <>
                                <HistoryCard>
                                    {earnings.data.map((earning) => (
                                        <div key={earning.id} className="flex items-center justify-between gap-4 p-4">
                                            <div>
                                                <p className="font-semibold text-slate-900 dark:text-white">{earning.itemName}</p>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {earning.itemType} · {formatDate(earning.createdAt)}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="font-bold text-emerald-600 dark:text-emerald-300">
                                                    {formatMoney(earning.sellerNetPaise)}
                                                </p>
                                                <AdminStatusBadge tone={statusTone(earning.status)}>{earning.status}</AdminStatusBadge>
                                            </div>
                                        </div>
                                    ))}
                                </HistoryCard>
                                <PaginationLinks paginator={earnings} />
                            </>
                        ) : (
                            <AdminEmptyState icon={Clock3} title="No earnings yet" description="Your marketplace sales earnings will appear here." />
                        )
                    ) : null}

                    {tab === 'ledger' ? (
                        transactions.data.length ? (
                            <>
                                <HistoryCard>
                                    {transactions.data.map((transaction) => (
                                        <div key={transaction.id} className="flex items-center justify-between gap-4 p-4">
                                            <div>
                                                <p className="font-semibold text-slate-900 dark:text-white">
                                                    {transaction.type.replaceAll('_', ' ')}
                                                </p>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {transaction.balanceBucket} · {formatDate(transaction.createdAt)}
                                                </p>
                                            </div>
                                            <p
                                                className={`font-bold ${transaction.amountPaise >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300'}`}
                                            >
                                                {transaction.amountPaise >= 0 ? '+' : ''}
                                                {formatMoney(transaction.amountPaise)}
                                            </p>
                                        </div>
                                    ))}
                                </HistoryCard>
                                <PaginationLinks paginator={transactions} />
                            </>
                        ) : (
                            <AdminEmptyState icon={ListRestart} title="No ledger entries" description="Wallet movements will appear here." />
                        )
                    ) : null}
                </section>
            </div>
        </AppLayout>
    );
}
