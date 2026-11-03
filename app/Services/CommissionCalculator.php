<?php

namespace App\Services;

use InvalidArgumentException;

class CommissionCalculator
{
    /**
     * @return array<string, int>
     */
    public function calculate(
        int $taxableAmountPaise,
        int $buyerPaidPaise,
        int $commissionBasisPoints,
        int $buyerGstPaise = 0,
        int $commissionGstPaise = 0,
        int $tdsPaise = 0,
        int $gstTcsPaise = 0,
        int $providerFeePaise = 0,
    ): array {
        if ($taxableAmountPaise < 0 || $buyerPaidPaise < 0) {
            throw new InvalidArgumentException('Money amounts cannot be negative.');
        }

        if ($commissionBasisPoints < 0 || $commissionBasisPoints > 10000) {
            throw new InvalidArgumentException('Commission must be between 0 and 10000 basis points.');
        }

        foreach ([$buyerGstPaise, $commissionGstPaise, $tdsPaise, $gstTcsPaise, $providerFeePaise] as $deduction) {
            if ($deduction < 0) {
                throw new InvalidArgumentException('Tax and fee amounts cannot be negative.');
            }
        }

        $commissionPaise = intdiv(($taxableAmountPaise * $commissionBasisPoints) + 5000, 10000);
        $sellerGrossPaise = $taxableAmountPaise - $commissionPaise;
        $sellerNetPaise = $sellerGrossPaise
            - $commissionGstPaise
            - $tdsPaise
            - $gstTcsPaise
            - $providerFeePaise;

        if ($sellerNetPaise < 0) {
            throw new InvalidArgumentException('Deductions cannot exceed the seller earning.');
        }

        return [
            'taxable_amount_paise' => $taxableAmountPaise,
            'buyer_gst_paise' => $buyerGstPaise,
            'buyer_paid_paise' => $buyerPaidPaise,
            'commission_basis_points' => $commissionBasisPoints,
            'commission_paise' => $commissionPaise,
            'seller_gross_paise' => $sellerGrossPaise,
            'commission_gst_paise' => $commissionGstPaise,
            'tds_paise' => $tdsPaise,
            'gst_tcs_paise' => $gstTcsPaise,
            'provider_fee_paise' => $providerFeePaise,
            'seller_net_paise' => $sellerNetPaise,
        ];
    }
}
