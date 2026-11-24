import { LoaderCircle, ReceiptIndianRupee } from 'lucide-react';
import { useEffect, useState } from 'react';

type Preview = {
    currency: string;
    paymentEnvironment: 'test' | 'live';
    isTestMode: boolean;
    itemPricePaise: number;
    buyerPayablePaise: number;
    platformCommission: {
        enabled: boolean;
        basisPoints: number;
        percent: number;
        amountPaise: number;
    };
    sellerGrossPaise: number;
    sellerDeductionsPaise: number;
    estimatedSellerEarningPaise: number;
    taxes: {
        buyerGst: { enabled: boolean; percent: number; amountPaise: number; note: string | null };
        commissionGst: { enabled: boolean; percent: number; amountPaise: number };
    };
    notice: string;
};

const formatMoney = (paise: number, currency: string) => new Intl.NumberFormat('en-IN', { style: 'currency', currency }).format(paise / 100);

export function MarketplacePricingPreview({ priceRupees }: { priceRupees: string }) {
    const [preview, setPreview] = useState<Preview | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const numericPrice = Number(priceRupees);
    const pricePaise = priceRupees.trim() !== '' && Number.isFinite(numericPrice) && numericPrice >= 0 ? Math.round(numericPrice * 100) : null;

    useEffect(() => {
        if (pricePaise === null) {
            setPreview(null);
            setError(null);
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setLoading(true);
            setError(null);

            try {
                const response = await fetch(`/api/v1/seller/pricing-preview?pricePaise=${pricePaise}`, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                    },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    setPreview(null);
                    setError('Pricing preview is unavailable. Refresh the page and try again.');
                    return;
                }

                const payload = (await response.json()) as { data?: Preview };
                setPreview(payload.data ?? null);
            } catch (error) {
                if (!(error instanceof DOMException && error.name === 'AbortError')) {
                    setPreview(null);
                    setError('Pricing preview is unavailable. Refresh the page and try again.');
                }
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [pricePaise]);

    if (pricePaise === null) return null;

    return (
        <div className="mt-4 rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 dark:border-indigo-900/60 dark:bg-indigo-950/20">
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <ReceiptIndianRupee className="size-4 text-indigo-600 dark:text-indigo-300" />
                    <p className="text-sm font-bold text-slate-900 dark:text-white">Estimated seller earnings</p>
                </div>
                {preview?.isTestMode ? (
                    <span className="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold tracking-wide text-amber-700 uppercase dark:bg-amber-950/60 dark:text-amber-300">
                        Test mode
                    </span>
                ) : null}
            </div>

            {loading && !preview ? (
                <div className="mt-4 flex items-center gap-2 text-sm text-slate-500">
                    <LoaderCircle className="size-4 animate-spin" /> Calculating preview...
                </div>
            ) : error ? (
                <p className="mt-3 text-sm text-rose-600 dark:text-rose-400">{error}</p>
            ) : preview ? (
                <>
                    <dl className="mt-4 space-y-2 text-sm">
                        <div className="flex items-center justify-between gap-3">
                            <dt className="text-slate-500">Selling price</dt>
                            <dd className="font-semibold text-slate-900 dark:text-white">{formatMoney(preview.itemPricePaise, preview.currency)}</dd>
                        </div>
                        {preview.taxes.buyerGst.enabled ? (
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-slate-500">GST charged to buyer ({preview.taxes.buyerGst.percent}%)</dt>
                                <dd className="font-semibold text-slate-900 dark:text-white">+{formatMoney(preview.taxes.buyerGst.amountPaise, preview.currency)}</dd>
                            </div>
                        ) : null}
                        <div className="flex items-center justify-between gap-3">
                            <dt className="font-medium text-slate-700 dark:text-slate-200">Buyer will pay</dt>
                            <dd className="font-semibold text-slate-900 dark:text-white">{formatMoney(preview.buyerPayablePaise, preview.currency)}</dd>
                        </div>
                        <div className="flex items-center justify-between gap-3">
                            <dt className="text-slate-500">Platform commission ({preview.platformCommission.percent}%)</dt>
                            <dd className="font-semibold text-rose-600">-{formatMoney(preview.platformCommission.amountPaise, preview.currency)}</dd>
                        </div>
                        {preview.taxes.commissionGst.enabled ? (
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-slate-500">GST on platform commission ({preview.taxes.commissionGst.percent}%)</dt>
                                <dd className="font-semibold text-rose-600">-{formatMoney(preview.taxes.commissionGst.amountPaise, preview.currency)}</dd>
                            </div>
                        ) : null}
                        <div className="flex items-center justify-between gap-3">
                            <dt className="text-slate-500">Seller earning before buyer GST</dt>
                            <dd className="font-semibold text-slate-900 dark:text-white">{formatMoney(preview.sellerGrossPaise, preview.currency)}</dd>
                        </div>
                        <div className="flex items-center justify-between gap-3 border-t border-indigo-100 pt-2 dark:border-indigo-900/60">
                            <dt className="font-bold text-slate-800 dark:text-slate-100">Estimated wallet credit</dt>
                            <dd className="text-base font-bold text-emerald-600">
                                {formatMoney(preview.estimatedSellerEarningPaise, preview.currency)}
                            </dd>
                        </div>
                    </dl>
                    <p className="mt-3 text-xs leading-5 text-slate-500">{preview.notice}</p>
                </>
            ) : null}
        </div>
    );
}
