<?php

namespace App\Services;

use App\Models\SiteSetting;
use RuntimeException;

class MarketplacePaymentSettings
{
    public function environment(): string
    {
        $environment = strtolower((string) config('designhub.payment_environment', 'test'));

        if (! in_array($environment, ['test', 'live'], true)) {
            throw new RuntimeException('PAYMENT_MODE must be either test or live.');
        }

        return $environment;
    }

    public function isRealMoney(): bool
    {
        return $this->environment() === 'live';
    }

    public function commissionBasisPoints(): int
    {
        return (int) (SiteSetting::current()->platform_commission_basis_points ?? 2500);
    }

    public function earningHoldDays(): int
    {
        return (int) (SiteSetting::current()->earning_hold_days ?? 7);
    }

    public function minimumWithdrawalPaise(): int
    {
        return (int) (SiteSetting::current()->minimum_withdrawal_paise ?? 50000);
    }

    public function sellerPayoutsEnabled(): bool
    {
        return (bool) (SiteSetting::current()->seller_payouts_enabled ?? false);
    }
}
