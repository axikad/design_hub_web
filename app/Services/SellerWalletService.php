<?php

namespace App\Services;

use App\Models\SaleEarning;
use App\Models\SellerLedgerEntry;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Carbon;

class SellerWalletService
{
    public function __construct(private readonly MarketplacePaymentSettings $settings) {}

    /** @return array<string, mixed> */
    public function summary(User $seller): array
    {
        $environment = $this->settings->environment();
        $balances = SellerLedgerEntry::query()
            ->where('seller_id', $seller->id)
            ->where('payment_environment', $environment)
            ->selectRaw('balance_bucket, SUM(amount_paise) as total_paise')
            ->groupBy('balance_bucket')
            ->pluck('total_paise', 'balance_bucket');

        $pending = (int) ($balances['pending'] ?? 0);
        $available = (int) ($balances['available'] ?? 0);
        $reserved = (int) ($balances['reserved'] ?? 0);
        $paid = (int) ($balances['paid'] ?? 0);
        $minimumWithdrawal = (int) ($this->settings->minimumWithdrawalPaise());
        $payoutsEnabled = $this->settings->sellerPayoutsEnabled();
        $settlementMode = $seller->seller_settlement_mode ?? 'manual';
        $hasOpenWithdrawal = Withdrawal::query()
            ->where('seller_id', $seller->id)
            ->where('payment_environment', $environment)
            ->whereIn('status', Withdrawal::OPEN_STATUSES)
            ->exists();
        $nextReleaseAt = SaleEarning::query()
            ->where('seller_id', $seller->id)
            ->where('payment_environment', $environment)
            ->where('status', 'pending')
            ->whereNotNull('available_at')
            ->min('available_at');

        return [
            'currency' => 'INR',
            'paymentEnvironment' => $environment,
            'isTestMode' => $environment === 'test',
            'payoutsEnabled' => $payoutsEnabled,
            'settlementMode' => $settlementMode,
            'manualWithdrawalsEnabled' => $settlementMode === 'manual',
            'balances' => [
                'pendingPaise' => $pending,
                'waitingSettlementPaise' => $pending,
                'availablePaise' => $available,
                'reservedPaise' => $reserved,
                'paidPaise' => $paid,
            ],
            'minimumWithdrawalPaise' => $minimumWithdrawal,
            'hasOpenWithdrawal' => $hasOpenWithdrawal,
            'canWithdraw' => $payoutsEnabled
                && $settlementMode === 'manual'
                && ! $hasOpenWithdrawal
                && $available >= $minimumWithdrawal,
            'nextReleaseAt' => $nextReleaseAt === null ? null : Carbon::parse($nextReleaseAt)->toIso8601String(),
            'nextSettlementAt' => $nextReleaseAt === null ? null : Carbon::parse($nextReleaseAt)->toIso8601String(),
        ];
    }
}
