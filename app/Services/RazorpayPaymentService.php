<?php

namespace App\Services;

use App\Models\Design;
use App\Models\PaymentOrder;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;

class RazorpayPaymentService
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

    public function createOrder(User $buyer, Design $design, ?string $buyerStateCode): array
    {
        $this->guardCheckout($buyer, $design);
        $environment = $this->settings->environment();
        $pricing = $this->checkoutPricing->preview(
            'design', $design->public_id, $design->title, (int) $design->price_paise,
            $design->owner, $buyerStateCode,
        );
        $amount = (int) $pricing['totalPayablePaise'];
        $commissionPaise = $this->gst->percentage((int) $design->price_paise, $this->settings->commissionBasisPoints());
        $commissionGstPaise = $this->gst->commissionGst(
            $commissionPaise,
            $this->settings->commissionGstEnabled(),
            $this->settings->commissionGstBasisPoints(),
        );

        $existing = PaymentOrder::query()
            ->where('buyer_id', $buyer->id)
            ->where('design_id', $design->id)
            ->where('payment_environment', $environment)
            ->where('amount_paise', $amount)
            ->where('buyer_state_code', $pricing['taxes']['buyerStateCode'])
            ->whereIn('status', ['created', 'pending'])
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $this->orderPayload($design, $existing);
        }

        $razorpayOrder = $this->client()->order->create([
            'receipt' => 'design_'.$design->public_id.'_'.now()->format('YmdHis'),
            'amount' => $amount,
            'currency' => 'INR',
            'payment_capture' => 1,
        ]);

        $order = PaymentOrder::query()->create([
            'buyer_id' => $buyer->id,
            'design_id' => $design->id,
            'razorpay_order_id' => $razorpayOrder['id'],
            'amount_paise' => $amount,
            'taxable_amount_paise' => $design->price_paise,
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
            'commission_basis_points' => $this->settings->commissionBasisPoints(),
            'commission_gst_basis_points' => $this->settings->commissionGstEnabled()
                ? $this->settings->commissionGstBasisPoints() : 0,
            'commission_gst_paise' => $commissionGstPaise,
            'tax_snapshot' => $pricing['taxes'],
            'status' => 'created',
        ]);

        return $this->orderPayload($design, $order);
    }

    public function verify(User $buyer, Design $design, string $orderId, string $paymentId, string $signature): array
    {
        $paymentOrder = PaymentOrder::query()
            ->where('buyer_id', $buyer->id)
            ->where('design_id', $design->id)
            ->where('razorpay_order_id', $orderId)
            ->where('payment_environment', $this->settings->environment())
            ->first();

        if ($paymentOrder === null) {
            throw new \InvalidArgumentException('Payment order not found for this design.');
        }

        $existing = $this->paidStatus($buyer, $design);
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

        return $this->reconcilePayment($buyer, $design, $paymentOrder, $payment, true);
    }

    public function purchaseStatus(User $buyer, Design $design): array
    {
        $paid = $this->paidStatus($buyer, $design);
        if ($paid !== null) {
            return $paid;
        }

        $paymentOrder = PaymentOrder::query()
            ->where('buyer_id', $buyer->id)
            ->where('design_id', $design->id)
            ->where('payment_environment', $this->settings->environment())
            ->latest('id')
            ->first();

        if ($paymentOrder === null) {
            return ['designId' => $design->public_id, 'status' => 'unpaid'];
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

            return ['designId' => $design->public_id, 'status' => 'unpaid'];
        }

        return $this->reconcilePayment($buyer, $design, $paymentOrder, $payment);
    }

    public function reconcileWebhookPayment(array $payment): void
    {
        $orderId = (string) ($payment['order_id'] ?? '');
        $paymentOrder = PaymentOrder::query()->where('razorpay_order_id', $orderId)->first();

        if ($paymentOrder === null) {
            return;
        }

        $this->reconcilePayment(
            $paymentOrder->buyer,
            $paymentOrder->design,
            $paymentOrder,
            $payment,
        );
    }

    private function reconcilePayment(
        User $buyer,
        Design $design,
        PaymentOrder $paymentOrder,
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
            $purchase = $this->completePurchase($buyer, $design, $paymentOrder, $paymentId);

            return [
                'designId' => $design->public_id,
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
            'designId' => $design->public_id,
            'status' => $verifiedCallback ? 'pending' : ($status === 'failed' ? 'unpaid' : 'pending'),
        ];
    }

    private function completePurchase(
        User $buyer,
        Design $design,
        PaymentOrder $paymentOrder,
        string $paymentId,
    ): Purchase {
        return DB::transaction(function () use ($buyer, $design, $paymentOrder, $paymentId) {
            $paymentOrder->update([
                'razorpay_payment_id' => $paymentId,
                'status' => 'paid',
                'last_reconciled_at' => now(),
            ]);

            $purchase = Purchase::query()->firstOrCreate(
                ['buyer_id' => $buyer->id, 'design_id' => $design->id],
                [
                    'payment_order_id' => $paymentOrder->id,
                    'razorpay_payment_id' => $paymentId,
                    'amount_paise' => $paymentOrder->amount_paise,
                    'currency' => $paymentOrder->currency,
                    'payment_environment' => $paymentOrder->payment_environment,
                    'status' => 'paid',
                    'purchased_at' => now(),
                ],
            );

            $this->earnings->createForDesignPurchase($purchase, $design);

            return $purchase;
        });
    }

    private function paidStatus(User $buyer, Design $design): ?array
    {
        $purchase = Purchase::query()
            ->where('buyer_id', $buyer->id)
            ->where('design_id', $design->id)
            ->where('status', 'paid')
            ->where('payment_environment', $this->settings->environment())
            ->first();

        return $purchase === null ? null : [
            'designId' => $design->public_id,
            'status' => 'paid',
            'purchaseId' => $purchase->public_id,
        ];
    }

    private function guardCheckout(User $buyer, Design $design): void
    {
        if ($design->deleted_at !== null || $design->price_paise === null) {
            throw new \InvalidArgumentException('This design is not available for purchase.');
        }

        if ($design->user_id === $buyer->id) {
            throw new \InvalidArgumentException('You cannot purchase your own design.');
        }

        if ($this->paidStatus($buyer, $design) !== null) {
            throw new \InvalidArgumentException('You already purchased this design.');
        }
    }

    private function orderPayload(Design $design, PaymentOrder $order): array
    {
        return [
            'designId' => $design->public_id,
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
