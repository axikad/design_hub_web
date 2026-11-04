<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaleEarning;
use App\Models\SellerLedgerEntry;
use App\Models\Withdrawal;
use App\Services\MarketplacePaymentSettings;
use App\Services\SellerWalletService;
use App\Services\SellerWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SellerWalletController extends Controller
{
    public function __construct(private readonly MarketplacePaymentSettings $settings) {}

    public function index(Request $request, SellerWalletService $wallets): Response
    {
        $seller = $request->user();
        $environment = $this->settings->environment();

        $earnings = SaleEarning::query()
            ->where('seller_id', $seller->id)
            ->where('payment_environment', $environment)
            ->latest('id')
            ->paginate(10, ['*'], 'earnings_page')
            ->withQueryString()
            ->through(fn (SaleEarning $earning) => [
                'id' => $earning->public_id,
                'itemName' => $earning->item_name,
                'itemType' => $earning->item_type,
                'buyerPaidPaise' => $earning->buyer_paid_paise,
                'commissionPaise' => $earning->commission_paise,
                'sellerNetPaise' => $earning->seller_net_paise,
                'status' => $earning->status,
                'availableAt' => $earning->available_at?->toIso8601String(),
                'createdAt' => $earning->created_at?->toIso8601String(),
            ]);

        $withdrawals = Withdrawal::query()
            ->where('seller_id', $seller->id)
            ->where('payment_environment', $environment)
            ->latest('id')
            ->paginate(10, ['*'], 'withdrawals_page')
            ->withQueryString()
            ->through(fn (Withdrawal $withdrawal) => [
                'id' => $withdrawal->public_id,
                'amountPaise' => $withdrawal->amount_paise,
                'netTransferPaise' => $withdrawal->net_transfer_paise,
                'status' => $withdrawal->status,
                'payoutMode' => $withdrawal->payout_mode,
                'canCancel' => $withdrawal->status === 'requested',
                'requestedAt' => $withdrawal->requested_at?->toIso8601String(),
            ]);

        $transactions = SellerLedgerEntry::query()
            ->where('seller_id', $seller->id)
            ->where('payment_environment', $environment)
            ->latest('id')
            ->paginate(10, ['*'], 'ledger_page')
            ->withQueryString()
            ->through(fn (SellerLedgerEntry $entry) => [
                'id' => $entry->public_id,
                'type' => $entry->entry_type,
                'balanceBucket' => $entry->balance_bucket,
                'amountPaise' => $entry->amount_paise,
                'createdAt' => $entry->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/wallet/index', [
            'wallet' => $wallets->summary($seller),
            'earnings' => $earnings,
            'withdrawals' => $withdrawals,
            'transactions' => $transactions,
            'activeTab' => $request->has('earnings_page')
                ? 'earnings'
                : ($request->has('ledger_page') ? 'ledger' : 'withdrawals'),
        ]);
    }

    public function store(Request $request, SellerWithdrawalService $withdrawals): RedirectResponse
    {
        $validated = $request->validate([
            'amountRupees' => ['required', 'numeric', 'min:0.01', 'max:10000000', 'decimal:0,2'],
        ]);

        $withdrawals->create(
            $request->user(),
            (int) round(((float) $validated['amountRupees']) * 100),
            'web-'.Str::uuid(),
        );

        return back()->with('success', 'Withdrawal requested successfully.');
    }

    public function cancel(
        Request $request,
        string $withdrawal,
        SellerWithdrawalService $withdrawals,
    ): RedirectResponse {
        $model = Withdrawal::query()
            ->where('public_id', $withdrawal)
            ->where('seller_id', $request->user()->id)
            ->where('payment_environment', $this->settings->environment())
            ->firstOrFail();

        $withdrawals->cancel($model, $request->user());

        return back()->with('success', 'Withdrawal cancelled and funds returned to your available balance.');
    }
}
