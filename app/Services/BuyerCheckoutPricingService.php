<?php

namespace App\Services;

use App\Models\User;

class BuyerCheckoutPricingService
{
    public function __construct(
        private readonly MarketplacePaymentSettings $settings,
        private readonly IndiaGstCalculator $gst,
    ) {}

    /** @return array<string, mixed> */
    public function preview(
        string $itemType,
        string $itemId,
        string $itemName,
        int $itemPricePaise,
        ?User $seller,
        ?string $buyerStateCode,
    ): array {
        $tax = $this->gst->buyerTax(
            $itemPricePaise,
            $seller,
            $buyerStateCode,
            $this->settings->buyerGstEnabled(),
            $this->settings->buyerGstBasisPoints(),
        );
        $lines = [];
        if ($tax['cgst_paise'] > 0) {
            $halfBasisPoints = intdiv((int) $tax['basis_points'], 2);
            $lines[] = ['type' => 'cgst', 'label' => 'CGST', 'basisPoints' => $halfBasisPoints, 'percent' => $halfBasisPoints / 100, 'amountPaise' => $tax['cgst_paise']];
            $lines[] = ['type' => 'sgst', 'label' => 'SGST', 'basisPoints' => (int) $tax['basis_points'] - $halfBasisPoints, 'percent' => ((int) $tax['basis_points'] - $halfBasisPoints) / 100, 'amountPaise' => $tax['sgst_paise']];
        } elseif ($tax['igst_paise'] > 0) {
            $lines[] = ['type' => 'igst', 'label' => 'IGST', 'basisPoints' => $tax['basis_points'], 'percent' => $tax['basis_points'] / 100, 'amountPaise' => $tax['igst_paise']];
        }

        return [
            'currency' => 'INR',
            'paymentEnvironment' => $this->settings->environment(),
            'isTestMode' => ! $this->settings->isRealMoney(),
            'item' => [
                'type' => $itemType,
                'id' => $itemId,
                'name' => $itemName,
            ],
            'itemPricePaise' => $itemPricePaise,
            'taxes' => [
                'enabled' => $tax['enabled'],
                'supplyType' => $tax['gst_supply_type'],
                'sellerStateCode' => $tax['seller_state_code'],
                'sellerStateName' => $tax['seller_state_name'],
                'buyerStateCode' => $tax['buyer_state_code'],
                'buyerStateName' => $tax['buyer_state_name'],
                'totalPaise' => $tax['buyer_gst_paise'],
                'lines' => $lines,
            ],
            'buyerFee' => [
                'enabled' => false,
                'amountPaise' => 0,
            ],
            'totalPayablePaise' => $tax['total_payable_paise'],
            'notice' => $tax['enabled']
                ? 'India GST is calculated by the backend from the seller and buyer billing states.'
                : 'No buyer GST is applied to this listing. The final payable amount is calculated by the backend.',
        ];
    }
}
