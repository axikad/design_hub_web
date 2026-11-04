import { AdminEmptyState } from '@/components/admin/admin-empty-state';
import { AdminFilterBar } from '@/components/admin/admin-filter-bar';
import { AdminSearchInput } from '@/components/admin/admin-search-input';
import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminTablePanel } from '@/components/admin/admin-table-panel';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Landmark, RotateCcw, Search } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type WithdrawalRow = {
    id: string;
    seller: { id: string; name: string | null; countryCode: string; mobileNumber: string; email: string | null } | null;
    amountPaise: number;
    currency: string;
    status: string;
    paymentEnvironment: 'test' | 'live';
    payoutMode: string;
    requestedAt: string | null;
};

type Props = {
    withdrawals: { data: WithdrawalRow[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search: string; status: string; environment: string };
    statusOptions: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Withdrawals', href: '/admin/withdrawals' },
];

function formatMoney(paise: number, currency: string): string {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency }).format(paise / 100);
}

function statusTone(status: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
    if (status === 'paid') return 'success';
    if (status === 'requested' || status === 'processing') return 'warning';
    if (status === 'approved') return 'info';
    if (status === 'rejected' || status === 'failed') return 'danger';
    return 'neutral';
}

export default function WithdrawalIndex({ withdrawals, filters, statusOptions }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');
    const [environment, setEnvironment] = useState(filters.environment || 'all');

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(
            route('admin.withdrawals.index'),
            {
                search: search || undefined,
                status: status === 'all' ? undefined : status,
                environment: environment === 'all' ? undefined : environment,
            },
            { preserveState: true, replace: true },
        );
    };

    const reset = () => {
        setSearch('');
        setStatus('all');
        setEnvironment('all');
        router.get(route('admin.withdrawals.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Seller withdrawals" />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader title="Seller withdrawals" description="Review seller withdrawal requests and safely move reserved balances." />

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
                            placeholder="Search withdrawal or seller..."
                        />
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-44 dark:border-slate-700">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                {statusOptions.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option.charAt(0).toUpperCase() + option.slice(1)}
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

                <AdminTablePanel footer={<PaginationLinks paginator={withdrawals} />}>
                    {withdrawals.data.length === 0 ? (
                        <AdminEmptyState
                            icon={Landmark}
                            title="No withdrawals found"
                            description="No seller withdrawal requests match these filters."
                        />
                    ) : (
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead className="bg-slate-50/80 dark:bg-slate-950/60">
                                <tr>
                                    {['Seller', 'Amount', 'Environment', 'Status', 'Requested', 'Actions'].map((heading) => (
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
                                {withdrawals.data.map((withdrawal) => (
                                    <tr key={withdrawal.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10">
                                        <td className="px-5 py-4">
                                            <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                                {withdrawal.seller?.name || 'Deleted seller'}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-400">
                                                {withdrawal.seller
                                                    ? `${withdrawal.seller.countryCode}${withdrawal.seller.mobileNumber}`
                                                    : withdrawal.id}
                                            </p>
                                        </td>
                                        <td className="px-5 py-4 text-sm font-bold text-slate-900 dark:text-white">
                                            {formatMoney(withdrawal.amountPaise, withdrawal.currency)}
                                        </td>
                                        <td className="px-5 py-4">
                                            <AdminStatusBadge tone={withdrawal.paymentEnvironment === 'test' ? 'warning' : 'success'}>
                                                {withdrawal.paymentEnvironment}
                                            </AdminStatusBadge>
                                            <p className="mt-1 text-xs text-slate-400">{withdrawal.payoutMode}</p>
                                        </td>
                                        <td className="px-5 py-4">
                                            <AdminStatusBadge tone={statusTone(withdrawal.status)}>{withdrawal.status}</AdminStatusBadge>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-500">
                                            {withdrawal.requestedAt ? new Date(withdrawal.requestedAt).toLocaleString() : '—'}
                                        </td>
                                        <td className="px-5 py-4">
                                            <Button asChild variant="outline" size="sm" className="rounded-lg">
                                                <Link href={route('admin.withdrawals.show', withdrawal.id)}>
                                                    <Eye className="size-3.5" /> Review
                                                </Link>
                                            </Button>
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
