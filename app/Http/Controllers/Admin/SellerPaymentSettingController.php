<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerPaymentSettingController extends Controller
{
    public function edit(): Response
    {
        $settings = SiteSetting::current();

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
            ],
            'paymentEnvironment' => config('designhub.payment_environment', 'test'),
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
        ]);

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
        SiteSetting::forgetCache();

        return redirect()
            ->route('admin.settings.seller-payments.edit')
            ->with('success', 'Seller payment settings saved. New values apply only to future transactions.');
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
