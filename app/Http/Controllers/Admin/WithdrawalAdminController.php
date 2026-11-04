<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerLedgerEntry;
use App\Models\Withdrawal;
use App\Services\SellerWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $environment = (string) $request->string('environment');

        $withdrawals = Withdrawal::query()
            ->with('seller:id,public_id,name,country_code,mobile_number,email')
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('public_id', 'like', "%{$search}%")
                    ->orWhereHas('seller', fn ($sellerQuery) => $sellerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($environment !== '', fn ($query) => $query->where('payment_environment', $environment))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Withdrawal $withdrawal) => $this->summary($withdrawal));

        return Inertia::render('admin/withdrawals/index', [
            'withdrawals' => $withdrawals,
            'filters' => compact('search', 'status', 'environment'),
            'statusOptions' => ['requested', 'approved', 'processing', 'paid', 'rejected', 'cancelled', 'failed'],
        ]);
    }

    public function show(string $withdrawal): Response
    {
        $model = $this->findWithdrawal($withdrawal);
        $model->load([
            'seller:id,public_id,name,country_code,mobile_number,email',
            'approvedBy:id,public_id,name',
            'histories' => fn ($query) => $query->with('actor:id,public_id,name')->oldest('id'),
            'ledgerEntries' => fn ($query) => $query->oldest('id'),
        ]);

        $balances = SellerLedgerEntry::query()
            ->where('seller_id', $model->seller_id)
            ->where('payment_environment', $model->payment_environment)
            ->selectRaw('balance_bucket, SUM(amount_paise) as balance')
            ->groupBy('balance_bucket')
            ->pluck('balance', 'balance_bucket');

        return Inertia::render('admin/withdrawals/show', [
            'withdrawal' => [
                ...$this->summary($model),
                'feePaise' => $model->fee_paise,
                'netTransferPaise' => $model->net_transfer_paise,
                'approvedAt' => $model->approved_at?->toIso8601String(),
                'processedAt' => $model->processed_at?->toIso8601String(),
                'cancelledAt' => $model->cancelled_at?->toIso8601String(),
                'approvedBy' => $model->approvedBy ? [
                    'id' => $model->approvedBy->public_id,
                    'name' => $model->approvedBy->name,
                ] : null,
                'rejectionReason' => $model->rejection_reason,
                'provider' => $model->provider,
                'providerTransferId' => $model->provider_transfer_id,
                'providerStatus' => $model->provider_status,
                'balances' => [
                    'pendingPaise' => (int) ($balances['pending'] ?? 0),
                    'availablePaise' => (int) ($balances['available'] ?? 0),
                    'reservedPaise' => (int) ($balances['reserved'] ?? 0),
                    'paidPaise' => (int) ($balances['paid'] ?? 0),
                ],
                'history' => $model->histories->map(fn ($history) => [
                    'id' => $history->id,
                    'fromStatus' => $history->from_status,
                    'toStatus' => $history->to_status,
                    'note' => $history->note,
                    'actor' => $history->actor ? [
                        'id' => $history->actor->public_id,
                        'name' => $history->actor->name,
                    ] : null,
                    'createdAt' => $history->created_at->toIso8601String(),
                ])->values(),
                'ledgerEntries' => $model->ledgerEntries->map(fn ($entry) => [
                    'id' => $entry->public_id,
                    'entryType' => $entry->entry_type,
                    'balanceBucket' => $entry->balance_bucket,
                    'amountPaise' => $entry->amount_paise,
                    'createdAt' => $entry->created_at->toIso8601String(),
                ])->values(),
                'actions' => [
                    'canApprove' => $model->status === 'requested',
                    'canReject' => in_array($model->status, ['requested', 'approved'], true),
                    'canCompleteSimulated' => $model->status === 'approved'
                        && $model->payment_environment === 'test'
                        && $model->payout_mode === 'simulated',
                ],
            ],
        ]);
    }

    public function approve(Request $request, string $withdrawal, SellerWithdrawalService $service): RedirectResponse
    {
        $validated = $this->validateNote($request);
        $service->approve($this->findWithdrawal($withdrawal), $request->user(), $validated['note']);

        return back()->with('success', 'Withdrawal approved. Funds remain reserved until payout completion.');
    }

    public function reject(Request $request, string $withdrawal, SellerWithdrawalService $service): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $service->reject($this->findWithdrawal($withdrawal), $request->user(), $validated['note']);

        return back()->with('success', 'Withdrawal rejected and reserved funds returned to the seller.');
    }

    public function completeSimulated(Request $request, string $withdrawal, SellerWithdrawalService $service): RedirectResponse
    {
        $validated = $this->validateNote($request);
        $service->completeSimulated($this->findWithdrawal($withdrawal), $request->user(), $validated['note']);

        return back()->with('success', 'Test withdrawal marked as paid and moved to the paid balance.');
    }

    private function findWithdrawal(string $publicId): Withdrawal
    {
        return Withdrawal::query()->where('public_id', $publicId)->firstOrFail();
    }

    /** @return array{note: string} */
    private function validateNote(Request $request): array
    {
        return $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(Withdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->public_id,
            'seller' => $withdrawal->seller ? [
                'id' => $withdrawal->seller->public_id,
                'name' => $withdrawal->seller->name,
                'countryCode' => $withdrawal->seller->country_code,
                'mobileNumber' => $withdrawal->seller->mobile_number,
                'email' => $withdrawal->seller->email,
            ] : null,
            'amountPaise' => $withdrawal->amount_paise,
            'currency' => $withdrawal->currency,
            'status' => $withdrawal->status,
            'paymentEnvironment' => $withdrawal->payment_environment,
            'payoutMode' => $withdrawal->payout_mode,
            'requestedAt' => $withdrawal->requested_at?->toIso8601String(),
        ];
    }
}
