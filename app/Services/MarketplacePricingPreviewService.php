<?php

namespace App\Services;

class MarketplacePricingPreviewService
{
    public function __construct(
        private readonly MarketplacePaymentSettings $settings,
        private readonly CommissionCalculator $calculator,
    ) {}

    /** @return array<string, mixed> */
    public function preview(int $pricePaise): array
    {
        $calculation = $this->calculator->calculate(
            taxableAmountPaise: $pricePaise,
            buyerPaidPaise: $pricePaise,
            commissionBasisPoints: $this->settings->commissionBasisPoints(),
        );

        return [
            'currency' => 'INR',
            'paymentEnvironment' => $this->settings->environment(),
            'isTestMode' => ! $this->settings->isRealMoney(),
            'itemPricePaise' => $pricePaise,
            'buyerPayablePaise' => $pricePaise,
            'platformCommission' => [
                'enabled' => true,
                'basisPoints' => $calculation['commission_basis_points'],
                'percent' => $calculation['commission_basis_points'] / 100,
                'amountPaise' => $calculation['commission_paise'],
            ],
            'sellerGrossPaise' => $calculation['seller_gross_paise'],
            'sellerDeductionsPaise' => 0,
            'estimatedSellerEarningPaise' => $calculation['seller_net_paise'],
            'taxes' => [
                'buyerGst' => ['enabled' => false, 'amountPaise' => 0],
                'commissionGst' => ['enabled' => false, 'amountPaise' => 0],
                'tds' => ['enabled' => false, 'amountPaise' => 0],
                'gstTcs' => ['enabled' => false, 'amountPaise' => 0],
            ],
            'notice' => 'This is an estimate using the current platform commission. Final values are snapshotted when the buyer pays.',
        ];
    }
}
