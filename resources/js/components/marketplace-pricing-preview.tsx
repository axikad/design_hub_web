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
    taxes: Record<string, { enabled: boolean; amountPaise: number }>;
    notice: string;
};

const formatMoney = (paise: number, currency: string) => new Intl.NumberFormat('en-IN', { style: 'currency', currency }).format(paise / 100);

export function MarketplacePricingPreview({ priceRupees }: { priceRupees: string }) {
    const [preview, setPreview] = useState<Preview | null>(null);
    const [loading, setLoading] = useState(false);
    const numericPrice = Number(priceRupees);
    const pricePaise = priceRupees.trim() !== '' && Number.isFinite(numericPrice) && numericPrice >= 0 ? Math.round(numericPrice * 100) : null;

    useEffect(() => {
        if (pricePaise === null) {
            setPreview(null);
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setLoading(true);

            try {
                const response = await fetch('/api/v1/seller/pricing-preview', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ pricePaise }),
                    signal: controller.signal,
                });

                if (!response.ok) {
                    setPreview(null);
                    return;
                }

                const payload = (await response.json()) as { data?: Preview };
                setPreview(payload.data ?? null);
            } catch (error) {
                if (!(error instanceof DOMException && error.name === 'AbortError')) {
                    setPreview(null);
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
            ) : preview ? (
                <>
                    <dl className="mt-4 space-y-2 text-sm">
                        <div className="flex items-center justify-between gap-3">
                            <dt className="text-slate-500">Selling price</dt>
                            <dd className="font-semibold text-slate-900 dark:text-white">{formatMoney(preview.itemPricePaise, preview.currency)}</dd>
                        </div>
                        <div className="flex items-center justify-between gap-3">
                            <dt className="text-slate-500">Platform commission ({preview.platformCommission.percent}%)</dt>
                            <dd className="font-semibold text-rose-600">-{formatMoney(preview.platformCommission.amountPaise, preview.currency)}</dd>
                        </div>
                        <div className="flex items-center justify-between gap-3 border-t border-indigo-100 pt-2 dark:border-indigo-900/60">
                            <dt className="font-bold text-slate-800 dark:text-slate-100">Estimated earning</dt>
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
