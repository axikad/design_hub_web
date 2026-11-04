<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MarketplacePricingPreviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerPricingController extends Controller
{
    public function preview(Request $request, MarketplacePricingPreviewService $pricing): JsonResponse
    {
        $validated = $request->validate([
            'pricePaise' => ['required', 'integer', 'min:0', 'max:9999999999'],
        ]);

        return ApiResponse::data(
            $pricing->preview((int) $validated['pricePaise']),
            message: 'Seller pricing preview calculated successfully.',
        );
    }
}
