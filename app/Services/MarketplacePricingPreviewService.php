<?php

namespace App\Services;

use App\Models\User;

class MarketplacePricingPreviewService
{
    public function __construct(
        private readonly MarketplacePaymentSettings $settings,
        private readonly CommissionCalculator $calculator,
        private readonly IndiaGstCalculator $gst,
    ) {}

    /** @return array<string, mixed> */
    public function preview(int $pricePaise, User $seller): array
    {
        $commissionPaise = $this->gst->percentage($pricePaise, $this->settings->commissionBasisPoints());
        $buyerGstPaise = $this->settings->buyerGstEnabled() && $seller->gst_registered
            ? $this->gst->percentage($pricePaise, $this->settings->buyerGstBasisPoints())
            : 0;
        $commissionGstPaise = $this->gst->commissionGst(
            $commissionPaise,
            $this->settings->commissionGstEnabled(),
            $this->settings->commissionGstBasisPoints(),
        );
        $calculation = $this->calculator->calculate(
            taxableAmountPaise: $pricePaise,
            buyerPaidPaise: $pricePaise + $buyerGstPaise,
            commissionBasisPoints: $this->settings->commissionBasisPoints(),
            buyerGstPaise: $buyerGstPaise,
            commissionGstPaise: $commissionGstPaise,
        );

        return [
            'currency' => 'INR',
            'paymentEnvironment' => $this->settings->environment(),
            'isTestMode' => ! $this->settings->isRealMoney(),
            'itemPricePaise' => $pricePaise,
            'buyerPayablePaise' => $pricePaise + $buyerGstPaise,
            'platformCommission' => [
                'enabled' => true,
                'basisPoints' => $calculation['commission_basis_points'],
                'percent' => $calculation['commission_basis_points'] / 100,
                'amountPaise' => $calculation['commission_paise'],
            ],
            'sellerGrossPaise' => $calculation['seller_gross_paise'],
            'sellerDeductionsPaise' => $commissionGstPaise,
            'estimatedSellerEarningPaise' => $calculation['seller_net_paise'],
            'taxes' => [
                'buyerGst' => [
                    'enabled' => $buyerGstPaise > 0,
                    'basisPoints' => $buyerGstPaise > 0 ? $this->settings->buyerGstBasisPoints() : 0,
                    'percent' => $buyerGstPaise > 0 ? $this->settings->buyerGstBasisPoints() / 100 : 0,
                    'amountPaise' => $buyerGstPaise,
                    'note' => $buyerGstPaise > 0 ? 'The final CGST/SGST or IGST split depends on the buyer billing state.' : null,
                ],
                'commissionGst' => [
                    'enabled' => $commissionGstPaise > 0,
                    'basisPoints' => $commissionGstPaise > 0 ? $this->settings->commissionGstBasisPoints() : 0,
                    'percent' => $commissionGstPaise > 0 ? $this->settings->commissionGstBasisPoints() / 100 : 0,
                    'amountPaise' => $commissionGstPaise,
                ],
                'tds' => ['enabled' => false, 'amountPaise' => 0],
                'gstTcs' => ['enabled' => false, 'amountPaise' => 0],
            ],
            'notice' => 'This estimate uses the current commission and India GST settings. Final values and billing states are snapshotted when the buyer pays.',
        ];
    }
}
