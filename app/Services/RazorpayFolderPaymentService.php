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

    public function createOrder(User $buyer, Folder $folder, ?string $buyerStateCode): array
    {
        $this->guardCheckout($buyer, $folder);
        $environment = $this->settings->environment();
        $pricing = $this->checkoutPricing->preview(
            'folder', $folder->public_id, $folder->name, (int) $folder->price_paise,
            $folder->owner, $buyerStateCode,
        );
        $amount = (int) $pricing['totalPayablePaise'];
        $commissionPaise = $this->gst->percentage((int) $folder->price_paise, $this->settings->commissionBasisPoints());
        $commissionGstPaise = $this->gst->commissionGst(
            $commissionPaise,
            $this->settings->commissionGstEnabled(),
            $this->settings->commissionGstBasisPoints(),
        );

        $existing = FolderPaymentOrder::query()
            ->where('buyer_id', $buyer->id)
            ->where('folder_id', $folder->id)
            ->where('payment_environment', $environment)
            ->where('amount_paise', $amount)
            ->where('buyer_state_code', $pricing['taxes']['buyerStateCode'])
            ->whereIn('status', ['created', 'pending'])
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $this->orderPayload($folder, $existing);
        }

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
            'taxable_amount_paise' => $folder->price_paise,
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

        return $this->orderPayload($folder, $order);
    }

    public function verify(User $buyer, Folder $folder, string $orderId, string $paymentId, string $signature): array
    {
        $paymentOrder = FolderPaymentOrder::query()
            ->where('buyer_id', $buyer->id)
            ->where('folder_id', $folder->id)
            ->where('razorpay_order_id', $orderId)
            ->where('payment_environment', $this->settings->environment())
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
            ->where('payment_environment', $this->settings->environment())
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

            $purchase = FolderPurchase::query()->firstOrCreate(
                ['buyer_id' => $buyer->id, 'folder_id' => $folder->id],
                [
                    'folder_payment_order_id' => $paymentOrder->id,
                    'razorpay_payment_id' => $paymentId,
                    'amount_paise' => $paymentOrder->amount_paise,
                    'currency' => $paymentOrder->currency,
                    'payment_environment' => $paymentOrder->payment_environment,
                    'status' => 'paid',
                    'purchased_at' => now(),
                ],
            );

            $this->earnings->createForFolderPurchase($purchase, $folder);

            return $purchase;
        });
    }

    private function paidStatus(User $buyer, Folder $folder): ?array
    {
        $purchase = FolderPurchase::query()
            ->where('buyer_id', $buyer->id)
            ->where('folder_id', $folder->id)
            ->where('status', 'paid')
            ->where('payment_environment', $this->settings->environment())
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

        if ($folder->user_id !== null && $folder->user_id === $buyer->id) {
            throw new \InvalidArgumentException('You cannot purchase your own folder.');
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
