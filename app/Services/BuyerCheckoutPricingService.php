<?php

namespace App\Services;

class BuyerCheckoutPricingService
{
    public function __construct(private readonly MarketplacePaymentSettings $settings) {}

    /** @return array<string, mixed> */
    public function preview(
        string $itemType,
        string $itemId,
        string $itemName,
        int $itemPricePaise,
    ): array {
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
                'enabled' => false,
                'totalPaise' => 0,
                'lines' => [],
            ],
            'buyerFee' => [
                'enabled' => false,
                'amountPaise' => 0,
            ],
            'totalPayablePaise' => $itemPricePaise,
            'notice' => 'No tax or additional buyer fee is currently applied. The final payable amount is calculated by the backend.',
        ];
    }
}
