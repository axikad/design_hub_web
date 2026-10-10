import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Props = {
    purchase: {
        id: string;
        status: string;
        amountPaise: number;
        currency: string;
        razorpayPaymentId: string | null;
        purchasedAt: string | null;
        buyer: { name: string | null; countryCode: string; mobileNumber: string } | null;
        design: { title: string; imageUri: string | null } | null;
    };
    statusOptions: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Purchases', href: '/admin/purchases' },
    { title: 'Edit purchase', href: '#' },
];

function formatInr(paise: number): string {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(paise / 100);
}

export default function AdminPurchaseForm({ purchase, statusOptions }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        status: purchase.status,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.purchases.update', purchase.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit purchase" />
            <div className="mx-auto max-w-2xl space-y-6">
                <AdminPageHeader title="Edit purchase" description="Update reconciliation status after verifying Razorpay." />
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <dl className="mb-6 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-slate-500">Design</dt>
                            <dd className="font-medium text-slate-900 dark:text-white">{purchase.design?.title ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Amount</dt>
                            <dd className="font-medium">{formatInr(purchase.amountPaise)}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Buyer</dt>
                            <dd>
                                {purchase.buyer
                                    ? `${purchase.buyer.name ?? 'User'} · ${purchase.buyer.countryCode}${purchase.buyer.mobileNumber}`
                                    : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Razorpay payment ID</dt>
                            <dd className="font-mono text-xs">{purchase.razorpayPaymentId ?? '—'}</dd>
                        </div>
                    </dl>
                    {purchase.design?.imageUri ? (
                        <img src={purchase.design.imageUri} alt="" className="mb-6 h-28 w-28 rounded-lg object-cover ring-1 ring-slate-200" />
                    ) : null}
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid max-w-xs gap-2">
                            <Label>Status</Label>
                            <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {statusOptions.map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {status}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.status} />
                        </div>
                        <div className="flex gap-3">
                            <Button type="submit" disabled={processing} className="bg-indigo-600 hover:bg-indigo-700">
                                Save status
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <a href={route('admin.purchases.index')}>Back</a>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
