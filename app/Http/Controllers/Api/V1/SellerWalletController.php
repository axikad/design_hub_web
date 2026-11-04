<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SaleEarning;
use App\Models\SellerLedgerEntry;
use App\Services\MarketplacePaymentSettings;
use App\Services\SellerWalletService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerWalletController extends Controller
{
    public function __construct(private readonly MarketplacePaymentSettings $settings) {}

    public function show(Request $request, SellerWalletService $wallets): JsonResponse
    {
        return ApiResponse::data(
            $wallets->summary($request->user()),
            message: 'Seller wallet retrieved successfully.',
        );
    }

    public function earnings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,available,reserved,paid,refunded,reversed,held'],
        ]);

        $rows = SaleEarning::query()
            ->where('seller_id', $request->user()->id)
            ->where('payment_environment', $this->settings->environment())
            ->when(
                $validated['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status),
            )
            ->latest('id')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated(
            $rows,
            $rows->getCollection()->map(fn (SaleEarning $earning) => [
                'id' => $earning->public_id,
                'item' => [
                    'type' => $earning->item_type,
                    'id' => $earning->item_public_id,
                    'name' => $earning->item_name,
                ],
                'currency' => $earning->currency,
                'buyerPaidPaise' => $earning->buyer_paid_paise,
                'commissionBasisPoints' => $earning->commission_basis_points,
                'commissionPercent' => $earning->commission_basis_points / 100,
                'commissionPaise' => $earning->commission_paise,
                'sellerGrossPaise' => $earning->seller_gross_paise,
                'deductionsPaise' => $earning->commission_gst_paise
                    + $earning->tds_paise
                    + $earning->gst_tcs_paise
                    + $earning->provider_fee_paise
                    + $earning->refund_paise,
                'sellerNetPaise' => $earning->seller_net_paise,
                'status' => $earning->status,
                'availableAt' => $earning->available_at?->toIso8601String(),
                'createdAt' => $earning->created_at->toIso8601String(),
            ])->values(),
            'Seller earnings retrieved successfully.',
        );
    }

    public function transactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'balanceBucket' => ['nullable', 'string', 'in:pending,available,reserved,paid'],
        ]);

        $rows = SellerLedgerEntry::query()
            ->with(['saleEarning:id,public_id', 'withdrawal:id,public_id'])
            ->where('seller_id', $request->user()->id)
            ->where('payment_environment', $this->settings->environment())
            ->when(
                $validated['balanceBucket'] ?? null,
                fn ($query, string $bucket) => $query->where('balance_bucket', $bucket),
            )
            ->latest('id')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated(
            $rows,
            $rows->getCollection()->map(fn (SellerLedgerEntry $entry) => [
                'id' => $entry->public_id,
                'earningId' => $entry->saleEarning?->public_id,
                'withdrawalId' => $entry->withdrawal?->public_id,
                'type' => $entry->entry_type,
                'balanceBucket' => $entry->balance_bucket,
                'amountPaise' => $entry->amount_paise,
                'currency' => $entry->currency,
                'metadata' => $entry->metadata,
                'createdAt' => $entry->created_at->toIso8601String(),
            ])->values(),
            'Seller transactions retrieved successfully.',
        );
    }
}
