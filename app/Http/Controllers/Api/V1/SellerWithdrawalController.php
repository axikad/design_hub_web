<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\MarketplacePaymentSettings;
use App\Services\SellerWithdrawalService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerWithdrawalController extends Controller
{
    public function __construct(private readonly MarketplacePaymentSettings $settings) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:requested,approved,processing,paid,rejected,cancelled,failed'],
        ]);

        $rows = Withdrawal::query()
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
            $rows->getCollection()->map(fn (Withdrawal $withdrawal) => $this->serialize($withdrawal))->values(),
            'Seller withdrawals retrieved successfully.',
        );
    }

    public function store(Request $request, SellerWithdrawalService $withdrawals): JsonResponse
    {
        $validated = $request->validate([
            'amountPaise' => ['required', 'integer', 'min:1'],
            'idempotencyKey' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        $result = $withdrawals->create(
            $request->user(),
            (int) $validated['amountPaise'],
            $validated['idempotencyKey'],
        );

        return ApiResponse::data(
            $this->serialize($result['withdrawal']),
            $result['created'] ? 201 : 200,
            $result['created']
                ? 'Withdrawal requested successfully.'
                : 'Existing withdrawal returned for this idempotency key.',
        );
    }

    public function show(Request $request, string $withdrawalId): JsonResponse
    {
        $withdrawal = $this->ownedWithdrawal($request, $withdrawalId)
            ->load('histories:id,withdrawal_id,from_status,to_status,note,created_at');

        return ApiResponse::data(
            $this->serialize($withdrawal, true),
            message: 'Seller withdrawal retrieved successfully.',
        );
    }

    public function cancel(
        Request $request,
        string $withdrawalId,
        SellerWithdrawalService $withdrawals,
    ): JsonResponse {
        $withdrawal = $this->ownedWithdrawal($request, $withdrawalId);
        $withdrawal = $withdrawals->cancel($withdrawal, $request->user());

        return ApiResponse::data(
            $this->serialize($withdrawal),
            message: 'Withdrawal cancelled and funds returned to available balance.',
        );
    }

    private function ownedWithdrawal(Request $request, string $publicId): Withdrawal
    {
        return Withdrawal::query()
            ->where('public_id', $publicId)
            ->where('seller_id', $request->user()->id)
            ->where('payment_environment', $this->settings->environment())
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function serialize(Withdrawal $withdrawal, bool $withHistory = false): array
    {
        $data = [
            'id' => $withdrawal->public_id,
            'amountPaise' => $withdrawal->amount_paise,
            'feePaise' => $withdrawal->fee_paise,
            'netTransferPaise' => $withdrawal->net_transfer_paise,
            'currency' => $withdrawal->currency,
            'status' => $withdrawal->status,
            'paymentEnvironment' => $withdrawal->payment_environment,
            'isTestMode' => $withdrawal->payment_environment === 'test',
            'payoutMode' => $withdrawal->payout_mode,
            'requestSource' => $withdrawal->request_source ?? 'seller',
            'canCancel' => $withdrawal->status === 'requested' && $withdrawal->request_source !== 'automatic',
            'requestedAt' => $withdrawal->requested_at?->toIso8601String(),
            'cancelledAt' => $withdrawal->cancelled_at?->toIso8601String(),
            'approvedAt' => $withdrawal->approved_at?->toIso8601String(),
            'processedAt' => $withdrawal->processed_at?->toIso8601String(),
        ];

        if ($withHistory) {
            $data['history'] = $withdrawal->histories->map(fn ($history) => [
                'fromStatus' => $history->from_status,
                'toStatus' => $history->to_status,
                'note' => $history->note,
                'createdAt' => $history->created_at->toIso8601String(),
            ])->values();
        }

        return $data;
    }
}
