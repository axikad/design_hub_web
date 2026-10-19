import { AdminEmptyState } from '@/components/admin/admin-empty-state';
import { AdminFilterBar } from '@/components/admin/admin-filter-bar';
import { AdminSearchInput } from '@/components/admin/admin-search-input';
import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminTablePanel } from '@/components/admin/admin-table-panel';
import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CreditCard, Pencil, RotateCcw, Search } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type PurchaseRow = {
    id: string;
    amountPaise: number;
    currency: string;
    status: string;
    orderStatus: string | null;
    razorpayOrderId: string | null;
    razorpayPaymentId: string | null;
    purchasedAt: string | null;
    designTitle: string | null;
    designId: string | null;
    buyer: { name: string | null; countryCode: string; mobileNumber: string } | null;
};
type Props = {
    purchases: { data: PurchaseRow[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search: string; status: string };
    statusOptions: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Purchases', href: '/admin/purchases' },
];

function formatMoney(paise: number, currency: string): string {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency }).format(paise / 100);
}

function statusTone(status: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
    if (status === 'paid') return 'success';
    if (status === 'pending') return 'warning';
    if (status === 'failed') return 'danger';
    if (status === 'refunded') return 'info';
    return 'neutral';
}

export default function AdminPurchasesIndex({ purchases, filters, statusOptions }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canUpdate = permissions.includes('purchases.update');
    const canDelete = permissions.includes('purchases.delete');
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(
            route('admin.purchases.index'),
            { search: search || undefined, status: status === 'all' ? undefined : status },
            { preserveState: true, replace: true },
        );
    };
    const reset = () => {
        setSearch('');
        setStatus('all');
        router.get(route('admin.purchases.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Purchases" />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader title="Purchases" description="Review purchase records and server-side Razorpay payment state." />
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
                            placeholder="Search purchase, design, buyer, order..."
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
                    </AdminFilterBar>
                </form>

                <AdminTablePanel footer={<PaginationLinks paginator={purchases} />}>
                    {purchases.data.length === 0 ? (
                        <AdminEmptyState icon={CreditCard} title="No purchases found" description="No purchase records match the current filters." />
                    ) : (
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead className="bg-slate-50/80 dark:bg-slate-950/60">
                                <tr>
                                    {['Design', 'Buyer', 'Amount', 'Payment references', 'Status', 'Purchased', 'Actions'].map((heading) => (
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
                                {purchases.data.map((purchase) => (
                                    <tr key={purchase.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10">
                                        <td className="px-5 py-4">
                                            <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                                {purchase.designTitle || 'Deleted design'}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-400">{purchase.designId || purchase.id}</p>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                            {purchase.buyer ? (
                                                <>
                                                    <p className="font-semibold text-slate-800 dark:text-slate-100">
                                                        {purchase.buyer.name || 'User'}
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-400">
                                                        {purchase.buyer.countryCode}
                                                        {purchase.buyer.mobileNumber}
                                                    </p>
                                                </>
                                            ) : (
                                                'Deleted user'
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                            {formatMoney(purchase.amountPaise, purchase.currency)}
                                        </td>
                                        <td className="px-5 py-4">
                                            <p
                                                className="max-w-52 truncate font-mono text-xs text-slate-600 dark:text-slate-300"
                                                title={purchase.razorpayOrderId || ''}
                                            >
                                                {purchase.razorpayOrderId || 'No order ID'}
                                            </p>
                                            <p
                                                className="mt-1 max-w-52 truncate font-mono text-xs text-slate-400"
                                                title={purchase.razorpayPaymentId || ''}
                                            >
                                                {purchase.razorpayPaymentId || 'No payment ID'}
                                            </p>
                                        </td>
                                        <td className="px-5 py-4">
                                            <AdminStatusBadge tone={statusTone(purchase.status)}>{purchase.status}</AdminStatusBadge>
                                            {purchase.orderStatus && purchase.orderStatus !== purchase.status ? (
                                                <p className="mt-1 text-xs text-slate-400">Order: {purchase.orderStatus}</p>
                                            ) : null}
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-500">
                                            {purchase.purchasedAt ? new Date(purchase.purchasedAt).toLocaleString() : 'Not completed'}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex gap-2">
                                                {canUpdate ? (
                                                    <Button asChild variant="outline" size="sm" className="rounded-lg">
                                                        <Link href={route('admin.purchases.edit', purchase.id)}>
                                                            <Pencil className="size-3.5" /> Review
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                {canDelete ? (
                                                    <ConfirmDelete
                                                        title="Delete purchase record?"
                                                        description="Use only for incorrect test data. This does not issue a Razorpay refund."
                                                        onConfirm={() => router.delete(route('admin.purchases.destroy', purchase.id))}
                                                    />
                                                ) : null}
                                            </div>
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
