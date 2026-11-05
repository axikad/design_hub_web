import { AdminEmptyState } from '@/components/admin/admin-empty-state';
import { AdminFilterBar } from '@/components/admin/admin-filter-bar';
import { AdminSearchInput } from '@/components/admin/admin-search-input';
import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, Clock3, FlaskConical, LoaderCircle, PlayCircle, RotateCcw, Search, Settings2, WalletCards } from 'lucide-react';
import { type FormEventHandler, useEffect, useState } from 'react';

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

type Props = {
    earnings: { data: SettlementEarning[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search: string; status: string; cycle: string; environment: string };
    cycles: { key: string; startDay: number; endDay: number; settlementDay: number; count: number }[];
    legacyCount: number;
    allCount: number;
    summary: { waitingCount: number; waitingPaise: number; releasedCount: number; releasedPaise: number };
    paymentEnvironment: string;
    marketplaceTimezone: string;
    serverTime: string;
    statusOptions: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Seller payments', href: '/admin/settings/seller-payments' },
    { title: 'Settlement earnings', href: '/admin/settlement-earnings' },
];

export default function SettlementEarningsIndex({
    earnings,
    filters,
    cycles,
    legacyCount,
    allCount,
    summary,
    paymentEnvironment,
    marketplaceTimezone,
    serverTime,
    statusOptions,
}: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canUpdate = permissions.includes('seller_payments.update');
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');
    const [environment, setEnvironment] = useState(filters.environment || 'all');
    const [currentTime, setCurrentTime] = useState(() => new Date(serverTime));
    const [processingEarning, setProcessingEarning] = useState<string | null>(null);

    useEffect(() => {
        const timer = window.setInterval(() => setCurrentTime((value) => new Date(value.getTime() + 1000)), 1000);
        return () => window.clearInterval(timer);
    }, []);

    const visit = (overrides: Partial<Props['filters']> = {}) => {
        const next = {
            ...filters,
            search,
            status: status === 'all' ? '' : status,
            environment: environment === 'all' ? '' : environment,
            ...overrides,
        };
        router.get(
            route('admin.settlement-earnings.index'),
            {
                search: next.search || undefined,
                status: next.status || undefined,
                environment: next.environment || undefined,
                cycle: next.cycle === 'all' ? undefined : next.cycle,
            },
            { preserveState: true, replace: true },
        );
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        visit();
    };

    const reset = () => {
        setSearch('');
        setStatus('all');
        setEnvironment('all');
        router.get(route('admin.settlement-earnings.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Settlement earnings" />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="Settlement earnings"
                    description="See exactly which sales belong to each settlement cycle, when they become available, and what has already been released."
                    actions={
                        <Button asChild variant="outline" className="rounded-xl">
                            <Link href={route('admin.settings.seller-payments.edit')}>
                                <Settings2 className="size-4" /> Manage cycles
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        icon={Clock3}
                        label="Waiting settlement"
                        value={summary.waitingCount.toString()}
                        detail={formatMoney(summary.waitingPaise)}
                        tone="amber"
                    />
                    <SummaryCard
                        icon={CheckCircle2}
                        label="Released earnings"
                        value={summary.releasedCount.toString()}
                        detail={formatMoney(summary.releasedPaise)}
                        tone="emerald"
                    />
                    <SummaryCard icon={WalletCards} label="All earnings" value={allCount.toString()} detail="Across every cycle" tone="indigo" />
                    <SummaryCard
                        icon={CalendarClock}
                        label="Marketplace time"
                        value={formatTime(currentTime, marketplaceTimezone)}
                        detail={marketplaceTimezone}
                        tone="slate"
                    />
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p className="px-3 pt-2 pb-3 text-xs font-bold tracking-wide text-slate-500 uppercase">View by applied cycle</p>
                    <div className="flex gap-2 overflow-x-auto pb-1">
                        <CycleTab active={filters.cycle === 'all'} label="All records" count={allCount} onClick={() => visit({ cycle: 'all' })} />
                        {cycles.map((cycle) => (
                            <CycleTab
                                key={cycle.key}
                                active={filters.cycle === cycle.key}
                                label={`Sales ${cycle.startDay}–${cycle.endDay} → day ${cycle.settlementDay}`}
                                count={cycle.count}
                                onClick={() => visit({ cycle: cycle.key })}
                            />
                        ))}
                        {legacyCount > 0 ? (
                            <CycleTab
                                active={filters.cycle === 'legacy'}
                                label="Legacy rules"
                                count={legacyCount}
                                onClick={() => visit({ cycle: 'legacy' })}
                            />
                        ) : null}
                    </div>
                </section>

                <form onSubmit={submit}>
                    <AdminFilterBar
                        actions={
                            <>
                                <Button type="button" variant="ghost" className="rounded-xl" onClick={reset}>
                                    <RotateCcw className="size-4" /> Reset
                                </Button>
                                <Button type="submit" className="rounded-xl bg-indigo-600 hover:bg-indigo-700">
                                    <Search className="size-4" /> Apply
                                </Button>
                            </>
                        }
                    >
                        <AdminSearchInput
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onClear={() => setSearch('')}
                            placeholder="Search sale or seller..."
                        />
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-44 dark:border-slate-700">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                {statusOptions.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {statusLabel(option)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={environment} onValueChange={setEnvironment}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-40 dark:border-slate-700">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All environments</SelectItem>
                                <SelectItem value="test">Test</SelectItem>
                                <SelectItem value="live">Live</SelectItem>
                            </SelectContent>
                        </Select>
                    </AdminFilterBar>
                </form>

                {paymentEnvironment === 'test' ? (
                    <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                        <FlaskConical className="mt-0.5 size-5 shrink-0" />
                        <p>
                            <strong>Test mode:</strong> Process now uses the real release flow, but no real money is transferred.
                        </p>
                    </div>
                ) : null}

                {earnings.data.length === 0 ? (
                    <div className="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                        <AdminEmptyState
                            icon={WalletCards}
                            title="No settlement earnings found"
                            description="No earning records match the selected cycle and filters."
                        />
                    </div>
                ) : (
                    <div className="space-y-3">
                        {earnings.data.map((earning) => (
                            <article
                                key={earning.id}
                                className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-colors hover:border-indigo-200 md:p-5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-900"
                            >
                                <div className="flex flex-col gap-4 xl:flex-row xl:items-center">
                                    <div className="min-w-0 xl:w-[24%]">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="truncate font-bold text-slate-950 dark:text-white">{earning.itemName}</h2>
                                            <AdminStatusBadge tone={statusTone(earning.status)}>{statusLabel(earning.status)}</AdminStatusBadge>
                                        </div>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {earning.itemType} · sold {formatDateTime(earning.createdAt, marketplaceTimezone)}
                                        </p>
                                        <p className="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">{earning.seller.name}</p>
                                        <p className="text-xs text-slate-500 capitalize">
                                            {earning.seller.settlementMode} settlement · {earning.paymentEnvironment}
                                        </p>
                                    </div>

                                    <InfoBlock
                                        label="Applied cycle"
                                        value={cycleLabel(earning)}
                                        detail="Saved when buyer paid"
                                        icon={CalendarClock}
                                    />
                                    <InfoBlock
                                        label={earning.status === 'pending' ? 'Scheduled for' : 'Released on'}
                                        value={formatDateTime(earning.availableAt, marketplaceTimezone)}
                                        detail={marketplaceTimezone}
                                        icon={earning.status === 'pending' ? Clock3 : CheckCircle2}
                                    />

                                    <div className="rounded-xl bg-slate-50 p-3 xl:w-[20%] dark:bg-slate-950/60">
                                        <p className="text-xs font-medium text-slate-500">Money breakdown</p>
                                        <div className="mt-2 space-y-1 text-xs">
                                            <p className="flex justify-between gap-3">
                                                <span>Buyer paid</span>
                                                <strong>{formatMoney(earning.buyerPaidPaise)}</strong>
                                            </p>
                                            <p className="flex justify-between gap-3 text-rose-600">
                                                <span>Commission ({earning.commissionPercent}%)</span>
                                                <strong>−{formatMoney(earning.commissionPaise)}</strong>
                                            </p>
                                            <p className="flex justify-between gap-3 border-t border-slate-200 pt-1 font-bold text-emerald-600 dark:border-slate-800">
                                                <span>Seller earning</span>
                                                <span>{formatMoney(earning.sellerNetPaise)}</span>
                                            </p>
                                        </div>
                                    </div>

                                    <div className="xl:ml-auto">
                                        {canUpdate && earning.canProcessNow ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="w-full rounded-xl xl:w-auto"
                                                disabled={processingEarning !== null}
                                                onClick={() => processNow(earning, setProcessingEarning)}
                                            >
                                                {processingEarning === earning.id ? (
                                                    <LoaderCircle className="size-4 animate-spin" />
                                                ) : (
                                                    <PlayCircle className="size-4" />
                                                )}
                                                Process now
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>
                            </article>
                        ))}
                        <div className="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                            <PaginationLinks paginator={earnings} />
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function SummaryCard({
    icon: Icon,
    label,
    value,
    detail,
    tone,
}: {
    icon: typeof Clock3;
    label: string;
    value: string;
    detail: string;
    tone: 'amber' | 'emerald' | 'indigo' | 'slate';
}) {
    const tones = {
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
        indigo: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    };
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className={`mb-3 flex size-9 items-center justify-center rounded-xl ${tones[tone]}`}>
                <Icon className="size-4" />
            </div>
            <p className="text-xs font-medium text-slate-500">{label}</p>
            <p className="mt-1 text-2xl font-bold text-slate-950 dark:text-white">{value}</p>
            <p className="mt-1 text-xs text-slate-400">{detail}</p>
        </div>
    );
}

function CycleTab({ active, label, count, onClick }: { active: boolean; label: string; count: number; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`shrink-0 rounded-xl border px-4 py-2.5 text-left transition-colors ${active ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-indigo-950/30'}`}
        >
            <span className="block text-sm font-semibold">{label}</span>
            <span className={`text-xs ${active ? 'text-indigo-100' : 'text-slate-400'}`}>
                {count} record{count === 1 ? '' : 's'}
            </span>
        </button>
    );
}

function InfoBlock({ label, value, detail, icon: Icon }: { label: string; value: string; detail: string; icon: typeof Clock3 }) {
    return (
        <div className="flex gap-3 xl:w-[18%]">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                <Icon className="size-4" />
            </div>
            <div className="min-w-0">
                <p className="text-xs font-medium text-slate-500">{label}</p>
                <p className="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white">{value}</p>
                <p className="mt-0.5 text-xs text-slate-400">{detail}</p>
            </div>
        </div>
    );
}

function processNow(earning: SettlementEarning, setProcessing: (id: string | null) => void) {
    if (!window.confirm('Process this test earning now? This bypasses its scheduled date.')) return;
    setProcessing(earning.id);
    router.post(
        route('admin.settings.seller-payments.earnings.process-now', earning.id),
        {},
        { preserveScroll: true, onFinish: () => setProcessing(null) },
    );
}

function formatMoney(paise: number): string {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(paise / 100);
}
function formatTime(value: Date, timeZone: string): string {
    return new Intl.DateTimeFormat('en-IN', { hour: 'numeric', minute: '2-digit', second: '2-digit', timeZone }).format(value);
}
function formatDateTime(value: string | null, timeZone: string): string {
    if (!value) return 'Not scheduled';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? 'Not scheduled'
        : new Intl.DateTimeFormat('en-IN', { dateStyle: 'medium', timeStyle: 'short', timeZone }).format(date);
}
function cycleLabel(earning: SettlementEarning): string {
    if (earning.cycle.type === 'monthly_cycle' && earning.cycle.startDay && earning.cycle.endDay && earning.cycle.settlementDay)
        return `Sales ${earning.cycle.startDay}–${earning.cycle.endDay} → day ${earning.cycle.settlementDay}`;
    if (earning.cycle.holdDays !== null) return `Legacy hold: ${earning.cycle.holdDays} day(s)`;
    return 'Legacy settlement rule';
}
function statusLabel(status: string): string {
    return status === 'pending' ? 'Waiting settlement' : status.charAt(0).toUpperCase() + status.slice(1);
}
function statusTone(status: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
    if (status === 'available' || status === 'paid') return 'success';
    if (status === 'pending' || status === 'held') return 'warning';
    if (status === 'refunded' || status === 'reversed') return 'danger';
    if (status === 'reserved') return 'info';
    return 'neutral';
}
