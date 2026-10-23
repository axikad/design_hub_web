<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FolderPurchase;
use App\Models\Purchase;
use App\Services\DesignPresenter;
use App\Support\FolderFilePreview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $type = in_array((string) $request->string('type'), ['design', 'folder'], true)
            ? (string) $request->string('type')
            : '';

        $designPurchases = DB::table('purchases as purchases')
            ->leftJoin('users as users', 'users.id', '=', 'purchases.buyer_id')
            ->leftJoin('designs as items', 'items.id', '=', 'purchases.design_id')
            ->leftJoin('payment_orders as orders', 'orders.id', '=', 'purchases.payment_order_id')
            ->selectRaw("'design' as item_type")
            ->addSelect($this->purchaseColumns('purchases', 'users', 'items', 'orders', 'title'));

        $folderPurchases = DB::table('folder_purchases as purchases')
            ->leftJoin('users as users', 'users.id', '=', 'purchases.buyer_id')
            ->leftJoin('folders as items', 'items.id', '=', 'purchases.folder_id')
            ->leftJoin('folder_payment_orders as orders', 'orders.id', '=', 'purchases.folder_payment_order_id')
            ->selectRaw("'folder' as item_type")
            ->addSelect($this->purchaseColumns('purchases', 'users', 'items', 'orders', 'name'));

        $query = match ($type) {
            'design' => $designPurchases,
            'folder' => $folderPurchases,
            default => $designPurchases->unionAll($folderPurchases),
        };

        $purchases = DB::query()
            ->fromSub($query, 'purchase_rows')
            ->when($search !== '', fn ($builder) => $builder->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('payment_id', 'like', "%{$search}%")
                    ->orWhere('order_id', 'like', "%{$search}%")
                    ->orWhere('buyer_name', 'like', "%{$search}%")
                    ->orWhere('buyer_mobile', 'like', "%{$search}%")
                    ->orWhere('item_name', 'like', "%{$search}%")
                    ->orWhere('item_id', 'like', "%{$search}%");
            }))
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->orderByDesc('purchased_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($purchase) => [
                'id' => $purchase->id,
                'itemType' => $purchase->item_type,
                'itemName' => $purchase->item_name,
                'itemId' => $purchase->item_id,
                'buyer' => $purchase->buyer_id ? [
                    'name' => $purchase->buyer_name,
                    'countryCode' => $purchase->buyer_country_code,
                    'mobileNumber' => $purchase->buyer_mobile,
                ] : null,
                'amountPaise' => (int) $purchase->amount_paise,
                'currency' => $purchase->currency,
                'status' => $purchase->status,
                'razorpayOrderId' => $purchase->order_id,
                'razorpayPaymentId' => $purchase->payment_id,
                'orderStatus' => $purchase->order_status,
                'purchasedAt' => $purchase->purchased_at,
            ]);

        return Inertia::render('admin/purchases/index', [
            'purchases' => $purchases,
            'filters' => compact('search', 'status', 'type'),
            'statusOptions' => ['unpaid', 'pending', 'paid', 'failed', 'refunded'],
        ]);
    }

    public function edit(string $purchase): Response
    {
        $model = $this->findPurchase($purchase);
        $isFolder = $model instanceof FolderPurchase;
        $model->load($isFolder
            ? ['buyer', 'folder.previewFiles', 'paymentOrder']
            : ['buyer', 'design.owner', 'paymentOrder']);

        if ($isFolder) {
            $folder = $model->folder;
            $preview = $folder?->previewFiles->first();
            $item = $folder ? [
                'id' => $folder->public_id,
                'name' => $folder->name,
                'imageUri' => $preview ? FolderFilePreview::temporaryUrl($folder, $preview) : null,
            ] : null;
        } else {
            $design = $model->design
                ? $this->presenter->forUser($model->design, $model->buyer)
                : null;
            $item = $design ? [
                'id' => $design['id'],
                'name' => $design['displayName'] ?? $design['title'],
                'imageUri' => $design['imageUri'] ?? null,
            ] : null;
        }

        return Inertia::render('admin/purchases/form', [
            'purchase' => [
                'id' => $model->public_id,
                'itemType' => $isFolder ? 'folder' : 'design',
                'item' => $item,
                'status' => $model->status,
                'amountPaise' => $model->amount_paise,
                'currency' => $model->currency,
                'razorpayPaymentId' => $model->razorpay_payment_id,
                'razorpayOrderId' => $model->paymentOrder?->razorpay_order_id,
                'orderStatus' => $model->paymentOrder?->status,
                'lastReconciledAt' => $model->paymentOrder?->last_reconciled_at?->toIso8601String(),
                'purchasedAt' => $model->purchased_at?->toIso8601String(),
                'buyer' => $model->buyer?->toMobileArray(),
            ],
            'statusOptions' => ['paid', 'pending', 'refunded'],
        ]);
    }

    public function update(Request $request, string $purchase): RedirectResponse
    {
        $model = $this->findPurchase($purchase);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['paid', 'pending', 'refunded'])],
        ]);

        $model->update(['status' => $validated['status']]);

        return redirect()->route('admin.purchases.index')->with('success', 'Purchase updated.');
    }

    public function destroy(string $purchase): RedirectResponse
    {
        $this->findPurchase($purchase)->delete();

        return redirect()->route('admin.purchases.index')->with('success', 'Purchase record deleted.');
    }

    /** @return array<int, string> */
    private function purchaseColumns(string $purchase, string $user, string $item, string $order, string $itemName): array
    {
        return [
            "{$purchase}.public_id as id",
            "{$purchase}.amount_paise",
            "{$purchase}.currency",
            "{$purchase}.status",
            "{$purchase}.razorpay_payment_id as payment_id",
            "{$purchase}.purchased_at",
            "{$purchase}.created_at",
            "{$user}.id as buyer_id",
            "{$user}.name as buyer_name",
            "{$user}.country_code as buyer_country_code",
            "{$user}.mobile_number as buyer_mobile",
            "{$item}.public_id as item_id",
            "{$item}.{$itemName} as item_name",
            "{$order}.razorpay_order_id as order_id",
            "{$order}.status as order_status",
        ];
    }

    private function findPurchase(string $publicId): Model
    {
        if (str_starts_with($publicId, 'folder_purchase_')) {
            return FolderPurchase::query()->where('public_id', $publicId)->firstOrFail();
        }

        return Purchase::query()->where('public_id', $publicId)->first()
            ?? FolderPurchase::query()->where('public_id', $publicId)->firstOrFail();
    }
}
