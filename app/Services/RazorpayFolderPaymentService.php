<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\FolderPaymentOrder;
use App\Models\FolderPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;

class RazorpayFolderPaymentService
{
    public function isConfigured(): bool
    {
        return filled(config('designhub.razorpay_key_id'))
            && filled(config('designhub.razorpay_key_secret'));
    }

    public function createOrder(User $buyer, Folder $folder): array
    {
        $this->guardCheckout($buyer, $folder);

        $existing = FolderPaymentOrder::query()
            ->where('buyer_id', $buyer->id)
            ->where('folder_id', $folder->id)
            ->whereIn('status', ['created', 'pending'])
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $this->orderPayload($folder, $existing);
        }

        $amount = (int) $folder->price_paise;
        $razorpayOrder = $this->client()->order->create([
            'receipt' => 'folder_'.$folder->id.'_'.now()->format('YmdHis'),
            'amount' => $amount,
            'currency' => 'INR',
            'payment_capture' => 1,
        ]);

        $order = FolderPaymentOrder::query()->create([
            'buyer_id' => $buyer->id,
            'folder_id' => $folder->id,
            'razorpay_order_id' => $razorpayOrder['id'],
            'amount_paise' => $amount,
            'currency' => 'INR',
            'status' => 'created',
        ]);

        return $this->orderPayload($folder, $order);
    }

    public function verify(User $buyer, Folder $folder, string $orderId, string $paymentId, string $signature): array
    {
        $paymentOrder = FolderPaymentOrder::query()
            ->where('buyer_id', $buyer->id)
            ->where('folder_id', $folder->id)
            ->where('razorpay_order_id', $orderId)
            ->first();

        if ($paymentOrder === null) {
            throw new \InvalidArgumentException('Payment order not found for this folder.');
        }

        $existing = $this->paidStatus($buyer, $folder);
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

        return $this->reconcilePayment($buyer, $folder, $paymentOrder, $payment, true);
    }

    public function purchaseStatus(User $buyer, Folder $folder): array
    {
        $paid = $this->paidStatus($buyer, $folder);
        if ($paid !== null) {
            return $paid;
        }

        $paymentOrder = FolderPaymentOrder::query()
            ->where('buyer_id', $buyer->id)
            ->where('folder_id', $folder->id)
            ->latest('id')
            ->first();

        if ($paymentOrder === null) {
            return ['folderId' => $folder->public_id, 'status' => 'unpaid'];
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

            return ['folderId' => $folder->public_id, 'status' => 'unpaid'];
        }

        return $this->reconcilePayment($buyer, $folder, $paymentOrder, $payment);
    }

    public function reconcileWebhookPayment(array $payment): void
    {
        $paymentOrder = FolderPaymentOrder::query()
            ->where('razorpay_order_id', (string) ($payment['order_id'] ?? ''))
            ->first();

        if ($paymentOrder === null) {
            return;
        }

        $this->reconcilePayment(
            $paymentOrder->buyer,
            $paymentOrder->folder,
            $paymentOrder,
            $payment,
        );
    }

    private function reconcilePayment(
        User $buyer,
        Folder $folder,
        FolderPaymentOrder $paymentOrder,
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
            $purchase = $this->completePurchase($buyer, $folder, $paymentOrder, $paymentId);

            return [
                'folderId' => $folder->public_id,
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
            'folderId' => $folder->public_id,
            'status' => $verifiedCallback ? 'pending' : ($status === 'failed' ? 'unpaid' : 'pending'),
        ];
    }

    private function completePurchase(
        User $buyer,
        Folder $folder,
        FolderPaymentOrder $paymentOrder,
        string $paymentId,
    ): FolderPurchase {
        return DB::transaction(function () use ($buyer, $folder, $paymentOrder, $paymentId) {
            $paymentOrder->update([
                'razorpay_payment_id' => $paymentId,
                'status' => 'paid',
                'last_reconciled_at' => now(),
            ]);

            return FolderPurchase::query()->firstOrCreate(
                ['buyer_id' => $buyer->id, 'folder_id' => $folder->id],
                [
                    'folder_payment_order_id' => $paymentOrder->id,
                    'razorpay_payment_id' => $paymentId,
                    'amount_paise' => $paymentOrder->amount_paise,
                    'currency' => $paymentOrder->currency,
                    'status' => 'paid',
                    'purchased_at' => now(),
                ],
            );
        });
    }

    private function paidStatus(User $buyer, Folder $folder): ?array
    {
        $purchase = FolderPurchase::query()
            ->where('buyer_id', $buyer->id)
            ->where('folder_id', $folder->id)
            ->where('status', 'paid')
            ->first();

        return $purchase === null ? null : [
            'folderId' => $folder->public_id,
            'status' => 'paid',
            'purchaseId' => $purchase->public_id,
        ];
    }

    private function guardCheckout(User $buyer, Folder $folder): void
    {
        if ($folder->price_paise < 100) {
            throw new \InvalidArgumentException('This folder is not available for purchase.');
        }

        if ($this->paidStatus($buyer, $folder) !== null) {
            throw new \InvalidArgumentException('You already purchased this folder.');
        }
    }

    private function orderPayload(Folder $folder, FolderPaymentOrder $order): array
    {
        return [
            'folderId' => $folder->public_id,
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
