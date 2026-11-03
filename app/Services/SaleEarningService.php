<?php

namespace App\Services;

use App\Models\Design;
use App\Models\Folder;
use App\Models\FolderPurchase;
use App\Models\Purchase;
use App\Models\SaleEarning;
use App\Models\SellerLedgerEntry;
use Illuminate\Support\Facades\DB;
use LogicException;

class SaleEarningService
{
    public function __construct(
        private readonly CommissionCalculator $calculator,
        private readonly MarketplacePaymentSettings $settings,
    ) {}

    public function createForDesignPurchase(Purchase $purchase, Design $design): SaleEarning
    {
        return $this->create(
            purchase: $purchase,
            sellerId: (int) $design->user_id,
            buyerId: (int) $purchase->buyer_id,
            itemType: 'design',
            itemId: (int) $design->id,
            itemPublicId: $design->public_id,
            itemName: $design->title,
            designPurchaseId: (int) $purchase->id,
            folderPurchaseId: null,
            commissionBasisPoints: (int) ($purchase->paymentOrder?->commission_basis_points
                ?? $this->settings->commissionBasisPoints()),
        );
    }

    public function createForFolderPurchase(FolderPurchase $purchase, Folder $folder): ?SaleEarning
    {
        if ($folder->user_id === null) {
            return null;
        }

        return $this->create(
            purchase: $purchase,
            sellerId: (int) $folder->user_id,
            buyerId: (int) $purchase->buyer_id,
            itemType: 'folder',
            itemId: (int) $folder->id,
            itemPublicId: $folder->public_id,
            itemName: $folder->name,
            designPurchaseId: null,
            folderPurchaseId: (int) $purchase->id,
            commissionBasisPoints: (int) ($purchase->paymentOrder?->commission_basis_points
                ?? $this->settings->commissionBasisPoints()),
        );
    }

    private function create(
        Purchase|FolderPurchase $purchase,
        int $sellerId,
        int $buyerId,
        string $itemType,
        int $itemId,
        string $itemPublicId,
        string $itemName,
        ?int $designPurchaseId,
        ?int $folderPurchaseId,
        int $commissionBasisPoints,
    ): SaleEarning {
        if ($sellerId === $buyerId) {
            throw new LogicException('A seller cannot earn from purchasing their own item.');
        }

        return DB::transaction(function () use (
            $purchase,
            $sellerId,
            $buyerId,
            $itemType,
            $itemId,
            $itemPublicId,
            $itemName,
            $designPurchaseId,
            $folderPurchaseId,
            $commissionBasisPoints,
        ): SaleEarning {
            $breakdown = $this->calculator->calculate(
                taxableAmountPaise: (int) $purchase->amount_paise,
                buyerPaidPaise: (int) $purchase->amount_paise,
                commissionBasisPoints: $commissionBasisPoints,
            );

            $identity = $designPurchaseId !== null
                ? ['design_purchase_id' => $designPurchaseId]
                : ['folder_purchase_id' => $folderPurchaseId];

            $earning = SaleEarning::query()->firstOrCreate(
                $identity,
                [
                    'seller_id' => $sellerId,
                    'buyer_id' => $buyerId,
                    'design_purchase_id' => $designPurchaseId,
                    'folder_purchase_id' => $folderPurchaseId,
                    'item_type' => $itemType,
                    'item_id' => $itemId,
                    'item_public_id' => $itemPublicId,
                    'item_name' => $itemName,
                    'currency' => $purchase->currency,
                    'payment_environment' => $purchase->payment_environment,
                    ...$breakdown,
                    'refund_paise' => 0,
                    'status' => 'pending',
                    'available_at' => now()->addDays($this->settings->earningHoldDays()),
                    'calculation_snapshot' => [
                        ...$breakdown,
                        'payment_environment' => $purchase->payment_environment,
                        'earning_hold_days' => $this->settings->earningHoldDays(),
                    ],
                ],
            );

            if (! $earning->wasRecentlyCreated) {
                return $earning;
            }

            SellerLedgerEntry::query()->firstOrCreate(
                ['idempotency_key' => 'sale_earning:'.$earning->public_id.':pending'],
                [
                    'seller_id' => $sellerId,
                    'sale_earning_id' => $earning->id,
                    'entry_type' => 'sale_earning_pending',
                    'balance_bucket' => 'pending',
                    'amount_paise' => $earning->seller_net_paise,
                    'currency' => $earning->currency,
                    'payment_environment' => $earning->payment_environment,
                    'metadata' => [
                        'item_type' => $itemType,
                        'item_public_id' => $itemPublicId,
                        'purchase_public_id' => $purchase->public_id,
                    ],
                ],
            );

            return $earning;
        });
    }
}
