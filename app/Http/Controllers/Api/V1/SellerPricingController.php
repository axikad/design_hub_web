<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MarketplacePricingPreviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerPricingController extends Controller
{
    public function preview(Request $request, MarketplacePricingPreviewService $pricing): JsonResponse
    {
        $validated = $request->validate([
            'pricePaise' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'sellerId' => ['sometimes', 'string', Rule::exists('users', 'public_id')],
        ]);

        $actor = $request->user();
        $seller = $actor;

        if (filled($validated['sellerId'] ?? null)) {
            abort_unless($actor->isSuperAdmin() || $validated['sellerId'] === $actor->public_id, 403);

            $seller = User::query()
                ->where('public_id', $validated['sellerId'])
                ->firstOrFail();
        }

        return ApiResponse::data(
            $pricing->preview((int) $validated['pricePaise'], $seller),
            message: 'Seller pricing preview calculated successfully.',
        );
    }
}
