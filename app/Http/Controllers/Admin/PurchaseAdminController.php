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

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $purchases = Purchase::query()
            ->with(['buyer', 'design.owner', 'paymentOrder'])
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('public_id', 'like', "%{$search}%")
                    ->orWhere('razorpay_payment_id', 'like', "%{$search}%")
                    ->orWhereHas('buyer', fn ($buyerQuery) => $buyerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%"))
                    ->orWhereHas('design', fn ($designQuery) => $designQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('public_id', 'like', "%{$search}%"))
                    ->orWhereHas('paymentOrder', fn ($orderQuery) => $orderQuery
                        ->where('razorpay_order_id', 'like', "%{$search}%"));
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('purchased_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Purchase $purchase) {
                return [
                    'id' => $purchase->public_id,
                    'buyer' => $purchase->buyer?->toMobileArray(),
                    'designTitle' => $purchase->design?->title,
                    'designId' => $purchase->design?->public_id,
                    'amountPaise' => $purchase->amount_paise,
                    'currency' => $purchase->currency,
                    'status' => $purchase->status,
                    'razorpayOrderId' => $purchase->paymentOrder?->razorpay_order_id,
                    'razorpayPaymentId' => $purchase->razorpay_payment_id,
                    'orderStatus' => $purchase->paymentOrder?->status,
                    'purchasedAt' => $purchase->purchased_at?->toIso8601String(),
                ];
            });

        return Inertia::render('admin/purchases/index', [
            'purchases' => $purchases,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => ['unpaid', 'pending', 'paid', 'failed', 'refunded'],
        ]);
    }

    public function edit(string $purchase): Response
    {
        $model = Purchase::query()
            ->with(['buyer', 'design.owner', 'paymentOrder'])
            ->where('public_id', $purchase)
            ->firstOrFail();

        return Inertia::render('admin/purchases/form', [
            'purchase' => [
                'id' => $model->public_id,
                'status' => $model->status,
                'amountPaise' => $model->amount_paise,
                'currency' => $model->currency,
                'razorpayPaymentId' => $model->razorpay_payment_id,
                'razorpayOrderId' => $model->paymentOrder?->razorpay_order_id,
                'orderStatus' => $model->paymentOrder?->status,
                'lastReconciledAt' => $model->paymentOrder?->last_reconciled_at?->toIso8601String(),
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
