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
        return (int) SiteSetting::current()->platform_commission_basis_points;
    }

    public function earningHoldDays(): int
    {
        return (int) SiteSetting::current()->earning_hold_days;
    }
}
