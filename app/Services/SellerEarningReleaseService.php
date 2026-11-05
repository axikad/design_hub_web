<?php

namespace App\Services;

use App\Models\SaleEarning;
use App\Models\SellerLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SellerEarningReleaseService
{
    public function __construct(private readonly SellerWithdrawalService $withdrawals) {}

    public function releaseEligible(string $environment, int $limit = 500): int
    {
        $earningIds = SaleEarning::query()
            ->where('payment_environment', $environment)
            ->where('status', 'pending')
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $released = 0;

        foreach ($earningIds as $earningId) {
            if ($this->release((int) $earningId, $environment)) {
                $released++;
                $earning = SaleEarning::query()->find($earningId);
                $seller = $earning === null ? null : User::query()->find($earning->seller_id);
                if ($seller?->usesAutomaticSettlement()) {
                    $this->withdrawals->createAutomatic(
                        $seller,
                        $earning->public_id,
                    );
                }
            }
        }

        if ($environment === app(MarketplacePaymentSettings::class)->environment()) {
            User::query()
                ->where('seller_settlement_mode', 'automatic')
                ->whereHas('sellerLedgerEntries', fn ($query) => $query->where('payment_environment', $environment))
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->each(function (User $seller) use ($environment): void {
                    $lastLedgerId = SellerLedgerEntry::query()
                        ->where('seller_id', $seller->id)
                        ->where('payment_environment', $environment)
                        ->max('id');
                    $this->withdrawals->createAutomatic($seller, 'ledger-'.$lastLedgerId);
                });
        }

        return $released;
    }

    public function release(int $earningId, string $environment): bool
    {
        return DB::transaction(function () use ($earningId, $environment): bool {
            $earning = SaleEarning::query()
                ->whereKey($earningId)
                ->where('payment_environment', $environment)
                ->lockForUpdate()
                ->first();

            if (
                $earning === null
                || $earning->status !== 'pending'
                || $earning->available_at === null
                || $earning->available_at->isFuture()
            ) {
                return false;
            }

            $metadata = [
                'earning_id' => $earning->public_id,
                'item_type' => $earning->item_type,
                'item_public_id' => $earning->item_public_id,
                'released_at' => now()->toIso8601String(),
            ];

            SellerLedgerEntry::query()->firstOrCreate(
                ['idempotency_key' => 'sale_earning:'.$earning->public_id.':release:pending'],
                [
                    'seller_id' => $earning->seller_id,
                    'sale_earning_id' => $earning->id,
                    'entry_type' => 'earning_release_pending_debit',
                    'balance_bucket' => 'pending',
                    'amount_paise' => -$earning->seller_net_paise,
                    'currency' => $earning->currency,
                    'payment_environment' => $earning->payment_environment,
                    'metadata' => $metadata,
                ],
            );

            SellerLedgerEntry::query()->firstOrCreate(
                ['idempotency_key' => 'sale_earning:'.$earning->public_id.':release:available'],
                [
                    'seller_id' => $earning->seller_id,
                    'sale_earning_id' => $earning->id,
                    'entry_type' => 'earning_release_available_credit',
                    'balance_bucket' => 'available',
                    'amount_paise' => $earning->seller_net_paise,
                    'currency' => $earning->currency,
                    'payment_environment' => $earning->payment_environment,
                    'metadata' => $metadata,
                ],
            );

            $earning->update(['status' => 'available']);

            return true;
        });
    }
}
