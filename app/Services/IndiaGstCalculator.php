<?php

namespace App\Services;

use App\Models\User;
use App\Support\IndiaGstState;
use InvalidArgumentException;

class IndiaGstCalculator
{
    /** @return array<string, int|string|bool|null> */
    public function buyerTax(
        int $taxableAmountPaise,
        ?User $seller,
        ?string $buyerStateCode,
        bool $enabled,
        int $basisPoints,
    ): array {
        $applies = $enabled && $basisPoints > 0 && $seller?->gst_registered;

        if (! $applies) {
            return $this->emptyBuyerTax($taxableAmountPaise, $seller?->tax_state_code, $buyerStateCode);
        }

        $sellerStateCode = $seller?->tax_state_code;
        if (! in_array($sellerStateCode, IndiaGstState::codes(), true)) {
            throw new InvalidArgumentException('The seller must configure a valid Indian GST state before GST checkout can be used.');
        }

        if (! in_array($buyerStateCode, IndiaGstState::codes(), true)) {
            throw new InvalidArgumentException('Select a valid Indian billing state to calculate GST.');
        }

        $gstPaise = $this->percentage($taxableAmountPaise, $basisPoints);
        $sameState = $sellerStateCode === $buyerStateCode;
        $cgstPaise = $sameState ? intdiv($gstPaise, 2) : 0;
        $sgstPaise = $sameState ? $gstPaise - $cgstPaise : 0;
        $igstPaise = $sameState ? 0 : $gstPaise;

        return [
            'enabled' => true,
            'taxable_amount_paise' => $taxableAmountPaise,
            'basis_points' => $basisPoints,
            'buyer_gst_paise' => $gstPaise,
            'cgst_paise' => $cgstPaise,
            'sgst_paise' => $sgstPaise,
            'igst_paise' => $igstPaise,
            'gst_supply_type' => $sameState ? 'intra_state' : 'inter_state',
            'seller_state_code' => $sellerStateCode,
            'seller_state_name' => IndiaGstState::name($sellerStateCode),
            'buyer_state_code' => $buyerStateCode,
            'buyer_state_name' => IndiaGstState::name($buyerStateCode),
            'total_payable_paise' => $taxableAmountPaise + $gstPaise,
        ];
    }

    public function commissionGst(int $commissionPaise, bool $enabled, int $basisPoints): int
    {
        return $enabled && $basisPoints > 0
            ? $this->percentage($commissionPaise, $basisPoints)
            : 0;
    }

    public function percentage(int $amountPaise, int $basisPoints): int
    {
        if ($amountPaise < 0 || $basisPoints < 0 || $basisPoints > 10000) {
            throw new InvalidArgumentException('Invalid GST calculation input.');
        }

        return intdiv(($amountPaise * $basisPoints) + 5000, 10000);
    }

    /** @return array<string, int|string|bool|null> */
    private function emptyBuyerTax(int $taxableAmountPaise, ?string $sellerStateCode, ?string $buyerStateCode): array
    {
        return [
            'enabled' => false,
            'taxable_amount_paise' => $taxableAmountPaise,
            'basis_points' => 0,
            'buyer_gst_paise' => 0,
            'cgst_paise' => 0,
            'sgst_paise' => 0,
            'igst_paise' => 0,
            'gst_supply_type' => 'none',
            'seller_state_code' => $sellerStateCode,
            'seller_state_name' => IndiaGstState::name($sellerStateCode),
            'buyer_state_code' => $buyerStateCode,
            'buyer_state_name' => IndiaGstState::name($buyerStateCode),
            'total_payable_paise' => $taxableAmountPaise,
        ];
    }
}
