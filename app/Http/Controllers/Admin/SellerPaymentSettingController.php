<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaleEarning;
use App\Models\SettlementCycle;
use App\Models\SiteSetting;
use App\Services\MarketplacePaymentSettings;
use App\Services\SellerEarningReleaseService;
use App\Services\SellerWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SellerPaymentSettingController extends Controller
{
    public function edit(): Response
    {
        $settings = SiteSetting::current();
        $timezone = (string) config('designhub.marketplace_timezone', 'Asia/Kolkata');
        $earnings = SaleEarning::query()
            ->with('seller:id,name,seller_settlement_mode')
            ->latest('id')
            ->paginate(15, ['*'], 'earningsPage')
            ->withQueryString()
            ->through(fn (SaleEarning $earning) => $this->serializeEarning($earning));

        return Inertia::render('admin/settings/seller-payments', [
            'settings' => [
                'sellerPayoutsEnabled' => (bool) ($settings->seller_payouts_enabled ?? false),
                'platformCommissionPercent' => $this->basisPointsToPercent((int) ($settings->platform_commission_basis_points ?? 2500)),
                'earningHoldDays' => (int) ($settings->earning_hold_days ?? 7),
                'minimumWithdrawalRupees' => $this->paiseToRupees((int) ($settings->minimum_withdrawal_paise ?? 50000)),
                'buyerGstEnabled' => (bool) ($settings->buyer_gst_enabled ?? false),
                'buyerGstPercent' => $this->basisPointsToPercent((int) ($settings->buyer_gst_basis_points ?? 0)),
                'commissionGstEnabled' => (bool) ($settings->commission_gst_enabled ?? false),
                'commissionGstPercent' => $this->basisPointsToPercent((int) ($settings->commission_gst_basis_points ?? 0)),
                'tdsEnabled' => (bool) ($settings->tds_enabled ?? false),
                'tdsPercent' => $this->basisPointsToPercent((int) ($settings->tds_basis_points ?? 0)),
                'gstTcsEnabled' => (bool) ($settings->gst_tcs_enabled ?? false),
                'gstTcsPercent' => $this->basisPointsToPercent((int) ($settings->gst_tcs_basis_points ?? 0)),
                'settlementCycles' => SettlementCycle::query()
                    ->orderBy('sort_order')
                    ->orderBy('start_day')
                    ->get()
                    ->map(fn (SettlementCycle $cycle) => [
                        'startDay' => $cycle->start_day,
                        'endDay' => $cycle->end_day,
                        'settlementDay' => $cycle->settlement_day,
                    ])->values()->all(),
            ],
            'paymentEnvironment' => config('designhub.payment_environment', 'test'),
            'marketplaceTimezone' => $timezone,
            'serverTime' => now()->setTimezone($timezone)->toIso8601String(),
            'earnings' => $earnings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sellerPayoutsEnabled' => ['required', 'boolean'],
            'platformCommissionPercent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'earningHoldDays' => ['required', 'integer', 'between:0,365'],
            'minimumWithdrawalRupees' => ['required', 'numeric', 'between:1,10000000', 'decimal:0,2'],
            'buyerGstEnabled' => ['required', 'boolean'],
            'buyerGstPercent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'commissionGstEnabled' => ['required', 'boolean'],
            'commissionGstPercent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'tdsEnabled' => ['required', 'boolean'],
            'tdsPercent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'gstTcsEnabled' => ['required', 'boolean'],
            'gstTcsPercent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'settlementCycles' => ['sometimes', 'array', 'max:31'],
            'settlementCycles.*.startDay' => ['required', 'integer', 'between:1,31'],
            'settlementCycles.*.endDay' => ['required', 'integer', 'between:1,31'],
            'settlementCycles.*.settlementDay' => ['required', 'integer', 'between:1,31'],
        ]);

        $submittedCycles = $validated['settlementCycles'] ?? SettlementCycle::query()
            ->orderBy('sort_order')->get()->map(fn (SettlementCycle $cycle) => [
                'startDay' => $cycle->start_day,
                'endDay' => $cycle->end_day,
                'settlementDay' => $cycle->settlement_day,
            ])->all();
        $cycles = collect($submittedCycles)->sortBy('startDay')->values();
        if ($cycles->isNotEmpty()) {
            $expectedStart = 1;
            foreach ($cycles as $index => $cycle) {
                if ($cycle['startDay'] !== $expectedStart || $cycle['endDay'] < $cycle['startDay']) {
                    throw ValidationException::withMessages([
                        "settlementCycles.{$index}.startDay" => 'Settlement cycles must cover days 1 through 31 continuously without gaps or overlaps.',
                    ]);
                }
                $expectedStart = $cycle['endDay'] + 1;
            }
            if ($expectedStart !== 32) {
                throw ValidationException::withMessages([
                    'settlementCycles' => 'Settlement cycles must cover every day from 1 through 31.',
                ]);
            }
        }

        DB::transaction(function () use ($validated, $cycles): void {
            SiteSetting::current()->update([
                'seller_payouts_enabled' => $validated['sellerPayoutsEnabled'],
                'platform_commission_basis_points' => $this->percentToBasisPoints($validated['platformCommissionPercent']),
                'earning_hold_days' => $validated['earningHoldDays'],
                'minimum_withdrawal_paise' => $this->rupeesToPaise($validated['minimumWithdrawalRupees']),
                'buyer_gst_enabled' => $validated['buyerGstEnabled'],
                'buyer_gst_basis_points' => $this->percentToBasisPoints($validated['buyerGstPercent']),
                'commission_gst_enabled' => $validated['commissionGstEnabled'],
                'commission_gst_basis_points' => $this->percentToBasisPoints($validated['commissionGstPercent']),
                'tds_enabled' => $validated['tdsEnabled'],
                'tds_basis_points' => $this->percentToBasisPoints($validated['tdsPercent']),
                'gst_tcs_enabled' => $validated['gstTcsEnabled'],
                'gst_tcs_basis_points' => $this->percentToBasisPoints($validated['gstTcsPercent']),
            ]);

            SettlementCycle::query()->delete();
            foreach ($cycles as $index => $cycle) {
                SettlementCycle::query()->create([
                    'start_day' => $cycle['startDay'],
                    'end_day' => $cycle['endDay'],
                    'settlement_day' => $cycle['settlementDay'],
                    'sort_order' => $index + 1,
                ]);
            }
        });
        SiteSetting::forgetCache();

        return redirect()
            ->route('admin.settings.seller-payments.edit')
            ->with('success', 'Seller payment settings saved. New values apply only to future transactions.');
    }

    public function processNow(
        SaleEarning $earning,
        MarketplacePaymentSettings $paymentSettings,
        SellerEarningReleaseService $releaseService,
        SellerWithdrawalService $withdrawalService,
    ): RedirectResponse {
        $environment = $paymentSettings->environment();

        abort_if($environment !== 'test' || $earning->payment_environment !== 'test', 403, 'Process now is available only in test mode.');

        if ($earning->status !== 'pending') {
            return back()->with('warning', 'This earning is no longer waiting for settlement.');
        }

        $payoutCreated = false;
        $released = DB::transaction(function () use ($earning, $releaseService, $withdrawalService, &$payoutCreated): bool {
            $lockedEarning = SaleEarning::query()->whereKey($earning->id)->lockForUpdate()->firstOrFail();
            if ($lockedEarning->status !== 'pending') {
                return false;
            }

            $lockedEarning->update(['available_at' => now()]);
            $released = $releaseService->release($lockedEarning->id, 'test');

            if (! $released) {
                return false;
            }

            $seller = $lockedEarning->seller()->firstOrFail();
            if ($seller->usesAutomaticSettlement()) {
                $payoutCreated = $withdrawalService->createAutomatic($seller, $lockedEarning->public_id) !== null;
            }

            return true;
        });

        if (! $released) {
            return back()->with('warning', 'The earning could not be processed because its status changed.');
        }

        $message = $earning->seller?->usesAutomaticSettlement()
            ? ($payoutCreated
                ? 'Test earning released and its automatic simulated payout was completed.'
                : 'Test earning released. No automatic payout was created; check whether seller payouts are enabled.')
            : 'Test earning moved from waiting settlement to the seller available balance.';

        return back()->with('success', $message);
    }

    /** @return array<string, mixed> */
    private function serializeEarning(SaleEarning $earning): array
    {
        $settlement = data_get($earning->calculation_snapshot, 'settlement', []);
        $cycleType = (string) data_get($settlement, 'type', 'legacy_hold');

        return [
            'id' => $earning->public_id,
            'itemName' => $earning->item_name,
            'itemType' => $earning->item_type,
            'seller' => [
                'name' => $earning->seller?->name ?? 'Unknown seller',
                'settlementMode' => $earning->seller?->seller_settlement_mode ?? 'manual',
            ],
            'buyerPaidPaise' => $earning->buyer_paid_paise,
            'commissionPaise' => $earning->commission_paise,
            'sellerNetPaise' => $earning->seller_net_paise,
            'commissionPercent' => $earning->commission_basis_points / 100,
            'status' => $earning->status,
            'paymentEnvironment' => $earning->payment_environment,
            'createdAt' => $earning->created_at?->toIso8601String(),
            'availableAt' => $earning->available_at?->toIso8601String(),
            'cycle' => [
                'type' => $cycleType,
                'startDay' => data_get($settlement, 'start_day'),
                'endDay' => data_get($settlement, 'end_day'),
                'settlementDay' => data_get($settlement, 'settlement_day'),
                'holdDays' => data_get($settlement, 'earning_hold_days'),
            ],
            'canProcessNow' => config('designhub.payment_environment', 'test') === 'test'
                && $earning->payment_environment === 'test'
                && $earning->status === 'pending',
        ];
    }

    private function basisPointsToPercent(int $basisPoints): string
    {
        return number_format($basisPoints / 100, 2, '.', '');
    }

    private function percentToBasisPoints(int|float|string $percent): int
    {
        return (int) round(((float) $percent) * 100);
    }

    private function paiseToRupees(int $paise): string
    {
        return number_format($paise / 100, 2, '.', '');
    }

    private function rupeesToPaise(int|float|string $rupees): int
    {
        return (int) round(((float) $rupees) * 100);
    }
}
