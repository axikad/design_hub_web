import { AdminPageHeader } from '@/components/admin/page-header';
import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type PurchaseRow = {
    id: string;
    amountPaise: number;
    status: string;
    purchasedAt: string | null;
    designTitle: string | null;
    designId: string | null;
    buyer: { name: string | null; countryCode: string; mobileNumber: string } | null;
};

type Props = {
    purchases: {
        data: PurchaseRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Purchases', href: '/admin/purchases' },
];

function formatInr(paise: number): string {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(paise / 100);
}

export default function AdminPurchasesIndex({ purchases }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Purchases" />
            <div className="space-y-6">
                <AdminPageHeader title="Purchases" description="Review payment records and update status when reconciling Razorpay." />
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                {['Design', 'Buyer', 'Amount', 'Status', 'Purchased', 'Actions'].map((heading) => (
                                    <th key={heading} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {purchases.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-sm text-slate-500">
                                        No purchases recorded yet.
                                    </td>
                                </tr>
                            ) : (
                                purchases.data.map((purchase) => (
                                    <tr key={purchase.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                                        <td className="px-4 py-3 text-sm font-medium text-slate-900 dark:text-slate-100">{purchase.designTitle ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                            {purchase.buyer
                                                ? `${purchase.buyer.name ?? 'User'} (${purchase.buyer.countryCode}${purchase.buyer.mobileNumber})`
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{formatInr(purchase.amountPaise)}</td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-md bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200">
                                                {purchase.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-slate-500">
                                            {purchase.purchasedAt ? new Date(purchase.purchasedAt).toLocaleString() : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-2">
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={route('admin.purchases.edit', purchase.id)}>Edit</Link>
                                                </Button>
                                                <ConfirmDelete
                                                    title="Delete purchase record?"
                                                    description="Use only for incorrect test data. This does not refund Razorpay automatically."
                                                    onConfirm={() => router.delete(route('admin.purchases.destroy', purchase.id))}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                    <PaginationLinks paginator={purchases} />
                </div>
            </div>
        </AppLayout>
    );
}
