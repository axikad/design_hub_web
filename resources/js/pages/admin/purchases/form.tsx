import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CreditCard, LoaderCircle, Save, ShieldCheck } from 'lucide-react';
import { type FormEventHandler } from 'react';

type Props = {
    purchase: {
        id: string;
        status: string;
        amountPaise: number;
        currency: string;
        razorpayPaymentId: string | null;
        razorpayOrderId: string | null;
        orderStatus: string | null;
        lastReconciledAt: string | null;
        purchasedAt: string | null;
        buyer: { name: string | null; countryCode: string; mobileNumber: string } | null;
        itemType: 'design' | 'folder';
        item: { id: string; name: string; imageUri: string | null } | null;
    };
    statusOptions: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Purchases', href: '/admin/purchases' },
    { title: 'Review purchase', href: '#' },
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

export default function AdminPurchaseForm({ purchase, statusOptions }: Props) {
    const { data, setData, put, processing, errors } = useForm({ status: purchase.status });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        put(route('admin.purchases.update', purchase.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Review purchase" />
            <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="Review purchase"
                    description="Inspect the server-side purchase and Razorpay references before changing status."
                    actions={
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.purchases.index')}>
                                <ArrowLeft className="size-4" /> Back
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                    <AdminFormPanel title="Purchase details" description={purchase.id} icon={CreditCard}>
                        <div className="flex items-start gap-4">
                            {purchase.item?.imageUri ? (
                                <img
                                    src={purchase.item.imageUri}
                                    alt=""
                                    className="size-24 rounded-2xl object-cover ring-1 ring-slate-200 dark:ring-slate-700"
                                />
                            ) : null}
                            <div>
                                <p className="text-lg font-bold text-slate-900 dark:text-white">
                                    {purchase.item?.name || `Deleted ${purchase.itemType}`}
                                </p>
                                <p className="mt-1 text-xs font-bold tracking-wide text-slate-400 uppercase">{purchase.itemType}</p>
                                <p className="mt-1 text-xl font-bold text-indigo-600 dark:text-indigo-300">
                                    {formatMoney(purchase.amountPaise, purchase.currency)}
                                </p>
                                <div className="mt-3">
                                    <AdminStatusBadge tone={statusTone(purchase.status)}>{purchase.status}</AdminStatusBadge>
                                </div>
                            </div>
                        </div>
                        <dl className="mt-6 grid gap-4 border-t border-slate-100 pt-5 text-sm sm:grid-cols-2 dark:border-slate-800">
                            <Detail
                                label="Buyer"
                                value={
                                    purchase.buyer
                                        ? `${purchase.buyer.name || 'User'} · ${purchase.buyer.countryCode}${purchase.buyer.mobileNumber}`
                                        : 'Deleted user'
                                }
                            />
                            <Detail
                                label="Purchased"
                                value={purchase.purchasedAt ? new Date(purchase.purchasedAt).toLocaleString() : 'Not completed'}
                            />
                            <Detail label="Razorpay order ID" value={purchase.razorpayOrderId || 'Not available'} mono />
                            <Detail label="Razorpay payment ID" value={purchase.razorpayPaymentId || 'Not available'} mono />
                            <Detail label="Provider order status" value={purchase.orderStatus || 'Unknown'} />
                            <Detail
                                label="Last reconciled"
                                value={purchase.lastReconciledAt ? new Date(purchase.lastReconciledAt).toLocaleString() : 'Not reconciled'}
                            />
                        </dl>
                    </AdminFormPanel>

                    <form onSubmit={submit}>
                        <AdminFormPanel
                            title="Reconciliation status"
                            description="Only update this after checking the payment provider state."
                            icon={ShieldCheck}
                        >
                            <div className="grid gap-2">
                                <Label>Status</Label>
                                <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                    <SelectTrigger className="h-11 rounded-xl border-slate-200 dark:border-slate-700">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statusOptions.map((status) => (
                                            <SelectItem key={status} value={status}>
                                                {status.charAt(0).toUpperCase() + status.slice(1)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.status} />
                            </div>
                            <p className="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                                Changing this record does not create a Razorpay payment or issue a refund. Payment verification remains server
                                controlled.
                            </p>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="mt-5 w-full rounded-xl bg-indigo-600 font-semibold hover:bg-indigo-700"
                            >
                                {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                                {processing ? 'Saving...' : 'Save status'}
                            </Button>
                        </AdminFormPanel>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}

function Detail({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
    return (
        <div>
            <dt className="text-xs font-semibold tracking-wide text-slate-400 uppercase">{label}</dt>
            <dd className={`mt-1 break-all text-slate-700 dark:text-slate-200 ${mono ? 'font-mono text-xs' : ''}`}>{value}</dd>
        </div>
    );
}
