<?php

namespace App\Services;

use App\Models\Catalog;
use App\Models\CatalogPaymentOrder;
use App\Models\CatalogPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;

class RazorpayCatalogPaymentService
{
    public function __construct(
        private readonly MarketplacePaymentSettings $settings,
        private readonly SaleEarningService $earnings,
        private readonly BuyerCheckoutPricingService $checkoutPricing,
        private readonly IndiaGstCalculator $gst,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('designhub.razorpay_key_id'))
            && filled(config('designhub.razorpay_key_secret'));
    }

    public function createOrder(User $buyer, Catalog $catalog, ?string $buyerStateCode): array
    {
        $this->guardCheckout($buyer, $catalog);
        $environment = $this->settings->environment();
        $pricing = $this->checkoutPricing->preview(
            'catalog', $catalog->public_id, $catalog->name, (int) $catalog->price_paise,
            $catalog->owner, $buyerStateCode,
        );
        $amount = (int) $pricing['totalPayablePaise'];
        $commissionBasisPoints = $this->settings->commissionBasisPoints($catalog->owner);
        $commissionPaise = $this->gst->percentage((int) $catalog->price_paise, $commissionBasisPoints);
        $commissionGstPaise = $this->gst->commissionGst(
            $commissionPaise,
            $this->settings->commissionGstEnabled(),
            $this->settings->commissionGstBasisPoints(),
        );
        $commissionGstBasisPoints = $this->settings->commissionGstEnabled()
            ? $this->settings->commissionGstBasisPoints() : 0;

        $existing = CatalogPaymentOrder::query()
            ->where('buyer_id', $buyer->id)
            ->where('catalog_id', $catalog->id)
            ->where('payment_environment', $environment)
            ->where('amount_paise', $amount)
            ->where('commission_basis_points', $commissionBasisPoints)
            ->where('commission_gst_basis_points', $commissionGstBasisPoints)
            ->where('buyer_state_code', $pricing['taxes']['buyerStateCode'])
            ->whereIn('status', ['created', 'pending'])
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $this->orderPayload($catalog, $existing);
        }

        $razorpayOrder = $this->client()->order->create([
            'receipt' => 'catalog_'.$catalog->id.'_'.now()->format('YmdHis'),
            'amount' => $amount,
            'currency' => 'INR',
            'payment_capture' => 1,
        ]);

        $order = CatalogPaymentOrder::query()->create([
            'buyer_id' => $buyer->id,
            'catalog_id' => $catalog->id,
            'razorpay_order_id' => $razorpayOrder['id'],
            'amount_paise' => $amount,
            'taxable_amount_paise' => $catalog->price_paise,
            'buyer_gst_basis_points' => (int) (($pricing['taxes']['lines'][0]['basisPoints'] ?? 0)
                + ($pricing['taxes']['lines'][1]['basisPoints'] ?? 0)),
            'buyer_gst_paise' => $pricing['taxes']['totalPaise'],
            'cgst_paise' => collect($pricing['taxes']['lines'])->firstWhere('type', 'cgst')['amountPaise'] ?? 0,
            'sgst_paise' => collect($pricing['taxes']['lines'])->firstWhere('type', 'sgst')['amountPaise'] ?? 0,
            'igst_paise' => collect($pricing['taxes']['lines'])->firstWhere('type', 'igst')['amountPaise'] ?? 0,
            'gst_supply_type' => $pricing['taxes']['supplyType'],
            'seller_state_code' => $pricing['taxes']['sellerStateCode'],
            'buyer_state_code' => $pricing['taxes']['buyerStateCode'],
            'currency' => 'INR',
            'payment_environment' => $environment,
            'commission_basis_points' => $commissionBasisPoints,
            'commission_gst_basis_points' => $commissionGstBasisPoints,
            'commission_gst_paise' => $commissionGstPaise,
            'tax_snapshot' => $pricing['taxes'],
            'status' => 'created',
        ]);

        return $this->orderPayload($catalog, $order);
    }

    public function verify(User $buyer, Catalog $catalog, string $orderId, string $paymentId, string $signature): array
    {
        $paymentOrder = CatalogPaymentOrder::query()
            ->where('buyer_id', $buyer->id)
            ->where('catalog_id', $catalog->id)
            ->where('razorpay_order_id', $orderId)
            ->where('payment_environment', $this->settings->environment())
            ->first();

        if ($paymentOrder === null) {
            throw new \InvalidArgumentException('Payment order not found for this catalog.');
        }

        $existing = $this->paidStatus($buyer, $catalog);
        if ($existing !== null) {
            return $existing;
        }

        $api = $this->client();
        $api->utility->verifyPaymentSignature([
            'razorpay_order_id' => $paymentOrder->razorpay_order_id,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ]);

        $payment = $api->payment->fetch($paymentId)->toArray();

        return $this->reconcilePayment($buyer, $catalog, $paymentOrder, $payment, true);
    }

    public function purchaseStatus(User $buyer, Catalog $catalog): array
    {
        $paid = $this->paidStatus($buyer, $catalog);
        if ($paid !== null) {
            return $paid;
        }

        $paymentOrder = CatalogPaymentOrder::query()
            ->where('buyer_id', $buyer->id)
            ->where('catalog_id', $catalog->id)
            ->where('payment_environment', $this->settings->environment())
            ->latest('id')
            ->first();

        if ($paymentOrder === null) {
            return ['catalogId' => $catalog->public_id, 'status' => 'unpaid'];
        }

        $payments = $this->client()->order
            ->fetch($paymentOrder->razorpay_order_id)
            ->payments()
            ->toArray();
        $items = $payments['items'] ?? [];

        $payment = collect($items)->first(
            fn (array $item) => ($item['status'] ?? null) === 'captured'
        ) ?? collect($items)->first(
            fn (array $item) => in_array($item['status'] ?? null, ['authorized', 'created'], true)
        );

        if ($payment === null) {
            $paymentOrder->update(['last_reconciled_at' => now()]);

            return ['catalogId' => $catalog->public_id, 'status' => 'unpaid'];
        }

        return $this->reconcilePayment($buyer, $catalog, $paymentOrder, $payment);
    }

    public function reconcileWebhookPayment(array $payment): void
    {
        $paymentOrder = CatalogPaymentOrder::query()
            ->where('razorpay_order_id', (string) ($payment['order_id'] ?? ''))
            ->first();

        if ($paymentOrder === null) {
            return;
        }

        $this->reconcilePayment(
            $paymentOrder->buyer,
            $paymentOrder->catalog,
            $paymentOrder,
            $payment,
        );
    }

    private function reconcilePayment(
        User $buyer,
        Catalog $catalog,
        CatalogPaymentOrder $paymentOrder,
        array $payment,
        bool $verifiedCallback = false,
    ): array {
        $paymentId = (string) ($payment['id'] ?? '');
        $matches = ($payment['order_id'] ?? null) === $paymentOrder->razorpay_order_id
            && (int) ($payment['amount'] ?? 0) === $paymentOrder->amount_paise
            && strtoupper((string) ($payment['currency'] ?? '')) === strtoupper($paymentOrder->currency);

        if (! $matches || $paymentId === '') {
            throw new \InvalidArgumentException('Payment details do not match the stored order.');
        }

        $status = (string) ($payment['status'] ?? '');
        if ($status === 'captured') {
            $purchase = $this->completePurchase($buyer, $catalog, $paymentOrder, $paymentId);

            return [
                'catalogId' => $catalog->public_id,
                'status' => 'paid',
                'purchaseId' => $purchase->public_id,
            ];
        }

        $paymentOrder->update([
            'razorpay_payment_id' => $paymentId,
            'status' => in_array($status, ['authorized', 'created'], true) ? 'pending' : 'failed',
            'last_reconciled_at' => now(),
        ]);

        return [
            'catalogId' => $catalog->public_id,
            'status' => $verifiedCallback ? 'pending' : ($status === 'failed' ? 'unpaid' : 'pending'),
        ];
    }

    private function completePurchase(
        User $buyer,
        Catalog $catalog,
        CatalogPaymentOrder $paymentOrder,
        string $paymentId,
    ): CatalogPurchase {
        return DB::transaction(function () use ($buyer, $catalog, $paymentOrder, $paymentId) {
            $paymentOrder->update([
                'razorpay_payment_id' => $paymentId,
                'status' => 'paid',
                'last_reconciled_at' => now(),
            ]);

            $purchase = CatalogPurchase::query()->firstOrCreate(
                ['buyer_id' => $buyer->id, 'catalog_id' => $catalog->id],
                [
                    'catalog_payment_order_id' => $paymentOrder->id,
                    'razorpay_payment_id' => $paymentId,
                    'amount_paise' => $paymentOrder->amount_paise,
                    'currency' => $paymentOrder->currency,
                    'payment_environment' => $paymentOrder->payment_environment,
                    'status' => 'paid',
                    'purchased_at' => now(),
                ],
            );

            $this->earnings->createForCatalogPurchase($purchase, $catalog);

            return $purchase;
        });
    }

    private function paidStatus(User $buyer, Catalog $catalog): ?array
    {
        $purchase = CatalogPurchase::query()
            ->where('buyer_id', $buyer->id)
            ->where('catalog_id', $catalog->id)
            ->where('status', 'paid')
            ->where('payment_environment', $this->settings->environment())
            ->first();

        return $purchase === null ? null : [
            'catalogId' => $catalog->public_id,
            'status' => 'paid',
            'purchaseId' => $purchase->public_id,
        ];
    }

    private function guardCheckout(User $buyer, Catalog $catalog): void
    {
        if ($catalog->price_paise < 100) {
            throw new \InvalidArgumentException('This catalog is not available for purchase.');
        }

        if ($catalog->user_id !== null && $catalog->user_id === $buyer->id) {
            throw new \InvalidArgumentException('You cannot purchase your own catalog.');
        }

        if ($this->paidStatus($buyer, $catalog) !== null) {
            throw new \InvalidArgumentException('You already purchased this catalog.');
        }
    }

    private function orderPayload(Catalog $catalog, CatalogPaymentOrder $order): array
    {
        return [
            'catalogId' => $catalog->public_id,
            'orderId' => $order->razorpay_order_id,
            'keyId' => config('designhub.razorpay_key_id'),
            'amount' => (int) $order->amount_paise,
            'currency' => $order->currency,
            'environment' => $order->payment_environment,
            'isRealMoney' => $order->payment_environment === 'live',
            'buyerStateCode' => $order->buyer_state_code,
        ];
    }

    private function client(): Api
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Razorpay is not configured on the server.');
        }

        return new Api(
            config('designhub.razorpay_key_id'),
            config('designhub.razorpay_key_secret'),
        );
    }
}
