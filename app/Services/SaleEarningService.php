<?php

namespace App\Services;

use App\Models\Catalog;
use App\Models\CatalogPurchase;
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
        private readonly SettlementScheduleService $settlements,
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
            catalogPurchaseId: null,
            commissionBasisPoints: (int) ($purchase->paymentOrder?->commission_basis_points
                ?? $this->settings->commissionBasisPoints($design->owner)),
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
            catalogPurchaseId: null,
            commissionBasisPoints: (int) ($purchase->paymentOrder?->commission_basis_points
                ?? $this->settings->commissionBasisPoints($folder->owner)),
        );
    }

    public function createForCatalogPurchase(CatalogPurchase $purchase, Catalog $catalog): ?SaleEarning
    {
        if ($catalog->user_id === null) {
            return null;
        }

        return $this->create(
            purchase: $purchase,
            sellerId: (int) $catalog->user_id,
            buyerId: (int) $purchase->buyer_id,
            itemType: 'catalog',
            itemId: (int) $catalog->id,
            itemPublicId: $catalog->public_id,
            itemName: $catalog->name,
            designPurchaseId: null,
            folderPurchaseId: null,
            catalogPurchaseId: (int) $purchase->id,
            commissionBasisPoints: (int) ($purchase->paymentOrder?->commission_basis_points
                ?? $this->settings->commissionBasisPoints($catalog->owner)),
        );
    }

    private function create(
        Purchase|FolderPurchase|CatalogPurchase $purchase,
        int $sellerId,
        int $buyerId,
        string $itemType,
        int $itemId,
        string $itemPublicId,
        string $itemName,
        ?int $designPurchaseId,
        ?int $folderPurchaseId,
        ?int $catalogPurchaseId,
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
            $catalogPurchaseId,
            $commissionBasisPoints,
        ): SaleEarning {
            $settlement = $this->settlements->scheduleFor(now());
            $paymentOrder = $purchase->paymentOrder;
            $taxableAmountPaise = (int) ($paymentOrder?->taxable_amount_paise ?: $purchase->amount_paise);
            $buyerGstPaise = (int) ($paymentOrder?->buyer_gst_paise ?? 0);
            $commissionGstPaise = (int) ($paymentOrder?->commission_gst_paise ?? 0);
            $breakdown = $this->calculator->calculate(
                taxableAmountPaise: $taxableAmountPaise,
                buyerPaidPaise: (int) $purchase->amount_paise,
                commissionBasisPoints: $commissionBasisPoints,
                buyerGstPaise: $buyerGstPaise,
                commissionGstPaise: $commissionGstPaise,
            );

            $identity = match (true) {
                $designPurchaseId !== null => ['design_purchase_id' => $designPurchaseId],
                $folderPurchaseId !== null => ['folder_purchase_id' => $folderPurchaseId],
                default => ['catalog_purchase_id' => $catalogPurchaseId],
            };

            $earning = SaleEarning::query()->firstOrCreate(
                $identity,
                [
                    'seller_id' => $sellerId,
                    'buyer_id' => $buyerId,
                    'design_purchase_id' => $designPurchaseId,
                    'folder_purchase_id' => $folderPurchaseId,
                    'catalog_purchase_id' => $catalogPurchaseId,
                    'item_type' => $itemType,
                    'item_id' => $itemId,
                    'item_public_id' => $itemPublicId,
                    'item_name' => $itemName,
                    'currency' => $purchase->currency,
                    'payment_environment' => $purchase->payment_environment,
                    ...$breakdown,
                    'buyer_gst_basis_points' => (int) ($paymentOrder?->buyer_gst_basis_points ?? 0),
                    'cgst_paise' => (int) ($paymentOrder?->cgst_paise ?? 0),
                    'sgst_paise' => (int) ($paymentOrder?->sgst_paise ?? 0),
                    'igst_paise' => (int) ($paymentOrder?->igst_paise ?? 0),
                    'gst_supply_type' => (string) ($paymentOrder?->gst_supply_type ?? 'none'),
                    'seller_state_code' => $paymentOrder?->seller_state_code,
                    'buyer_state_code' => $paymentOrder?->buyer_state_code,
                    'commission_gst_basis_points' => (int) ($paymentOrder?->commission_gst_basis_points ?? 0),
                    'refund_paise' => 0,
                    'status' => 'pending',
                    'available_at' => $settlement['availableAt'],
                    'calculation_snapshot' => [
                        ...$breakdown,
                        'tax' => $paymentOrder?->tax_snapshot,
                        'commission_gst_basis_points' => (int) ($paymentOrder?->commission_gst_basis_points ?? 0),
                        'payment_environment' => $purchase->payment_environment,
                        'settlement' => $settlement['snapshot'],
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
