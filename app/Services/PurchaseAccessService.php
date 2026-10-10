<?php

namespace App\Services;

use App\Models\Design;
use App\Models\Purchase;
use App\Models\User;

class PurchaseAccessService
{
    public function canDownloadStitch(User $user, Design $design): bool
    {
        if ($design->user_id === $user->id) {
            return true;
        }

        return Purchase::query()
            ->where('buyer_id', $user->id)
            ->where('design_id', $design->id)
            ->where('status', 'paid')
            ->exists();
    }

    public function purchaseStatus(User $buyer, Design $design): array
    {
        $purchase = Purchase::query()
            ->where('buyer_id', $buyer->id)
            ->where('design_id', $design->id)
            ->latest('id')
            ->first();

        if ($purchase === null) {
            return [
                'designId' => $design->public_id,
                'status' => 'unpaid',
            ];
        }

        $payload = [
            'designId' => $design->public_id,
            'status' => $purchase->status === 'paid' ? 'paid' : 'pending',
        ];

        if ($purchase->status === 'paid') {
            $payload['purchaseId'] = $purchase->public_id;
        }

        return $payload;
    }
}
