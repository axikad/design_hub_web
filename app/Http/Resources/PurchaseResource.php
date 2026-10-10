<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'design' => (new DesignResource($this->design))->resolve($request),
            'amountPaise' => $this->amount_paise,
            'currency' => $this->currency,
            'status' => $this->status,
            'purchasedAt' => $this->purchased_at?->utc()->toIso8601String(),
        ];
    }
}
