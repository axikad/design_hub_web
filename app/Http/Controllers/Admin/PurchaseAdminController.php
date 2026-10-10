<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Services\DesignPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseAdminController extends Controller
{
    public function __construct(private readonly DesignPresenter $presenter) {}

    public function index(): Response
    {
        $purchases = Purchase::query()
            ->with(['buyer', 'design.owner'])
            ->latest('purchased_at')
            ->paginate(20)
            ->through(function (Purchase $purchase) {
                return [
                    'id' => $purchase->public_id,
                    'buyer' => $purchase->buyer?->toMobileArray(),
                    'designTitle' => $purchase->design?->title,
                    'designId' => $purchase->design?->public_id,
                    'amountPaise' => $purchase->amount_paise,
                    'status' => $purchase->status,
                    'purchasedAt' => $purchase->purchased_at?->toIso8601String(),
                ];
            });

        return Inertia::render('admin/purchases/index', [
            'purchases' => $purchases,
        ]);
    }

    public function edit(string $purchase): Response
    {
        $model = Purchase::query()
            ->with(['buyer', 'design.owner'])
            ->where('public_id', $purchase)
            ->firstOrFail();

        return Inertia::render('admin/purchases/form', [
            'purchase' => [
                'id' => $model->public_id,
                'status' => $model->status,
                'amountPaise' => $model->amount_paise,
                'currency' => $model->currency,
                'razorpayPaymentId' => $model->razorpay_payment_id,
                'purchasedAt' => $model->purchased_at?->toIso8601String(),
                'buyer' => $model->buyer?->toMobileArray(),
                'design' => $model->design
                    ? $this->presenter->forUser($model->design, $model->buyer)
                    : null,
            ],
            'statusOptions' => ['paid', 'pending', 'refunded'],
        ]);
    }

    public function update(Request $request, string $purchase): RedirectResponse
    {
        $model = Purchase::query()->where('public_id', $purchase)->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', Rule::in(['paid', 'pending', 'refunded'])],
        ]);

        $model->update(['status' => $validated['status']]);

        return redirect()->route('admin.purchases.index')->with('success', 'Purchase updated.');
    }

    public function destroy(string $purchase): RedirectResponse
    {
        Purchase::query()->where('public_id', $purchase)->delete();

        return redirect()->route('admin.purchases.index')->with('success', 'Purchase record deleted.');
    }
}
