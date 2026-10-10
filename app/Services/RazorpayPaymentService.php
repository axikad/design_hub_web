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
    public function isConfigured(): bool
    {
        return filled(config('designhub.razorpay_key_id'))
            && filled(config('designhub.razorpay_key_secret'));
    }

    public function createOrder(User $buyer, Design $design): array
    {
        $this->guardCheckout($buyer, $design);

        $existing = PaymentOrder::query()
            ->where('buyer_id', $buyer->id)
            ->where('design_id', $design->id)
            ->whereIn('status', ['created', 'pending'])
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $this->orderPayload($design, $existing);
        }

        $amount = (int) $design->price_paise;
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
            'currency' => 'INR',
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

            return Purchase::query()->firstOrCreate(
                ['buyer_id' => $buyer->id, 'design_id' => $design->id],
                [
                    'payment_order_id' => $paymentOrder->id,
                    'razorpay_payment_id' => $paymentId,
                    'amount_paise' => $paymentOrder->amount_paise,
                    'currency' => $paymentOrder->currency,
                    'status' => 'paid',
                    'purchased_at' => now(),
                ],
            );
        });
    }

    private function paidStatus(User $buyer, Design $design): ?array
    {
        $purchase = Purchase::query()
            ->where('buyer_id', $buyer->id)
            ->where('design_id', $design->id)
            ->where('status', 'paid')
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
