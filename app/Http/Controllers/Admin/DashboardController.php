<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\FolderPaymentOrder;
use App\Models\FolderPurchase;
use App\Models\PaymentOrder;
use App\Models\Purchase;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $recentDesignPurchases = Purchase::query()
            ->with(['buyer', 'design'])
            ->latest('purchased_at')
            ->limit(5)
            ->get()
            ->map(fn (Purchase $purchase) => [
                'id' => $purchase->public_id,
                'itemType' => 'design',
                'itemName' => $purchase->design?->title,
                'buyer' => $purchase->buyer?->name,
                'amountPaise' => $purchase->amount_paise,
                'currency' => $purchase->currency,
                'status' => $purchase->status,
                'purchasedAt' => $purchase->purchased_at?->toIso8601String(),
            ]);

        $recentFolderPurchases = FolderPurchase::query()
            ->with(['buyer', 'folder'])
            ->latest('purchased_at')
            ->limit(5)
            ->get()
            ->map(fn (FolderPurchase $purchase) => [
                'id' => $purchase->public_id,
                'itemType' => 'folder',
                'itemName' => $purchase->folder?->name,
                'buyer' => $purchase->buyer?->name,
                'amountPaise' => $purchase->amount_paise,
                'currency' => $purchase->currency,
                'status' => $purchase->status,
                'purchasedAt' => $purchase->purchased_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'users' => User::query()->whereNotNull('phone_key')->count(),
                'designs' => Design::query()->listed()->count(),
                'purchases' => Purchase::query()->where('status', 'paid')->count()
                    + FolderPurchase::query()->where('status', 'paid')->count(),
                'pendingPayments' => PaymentOrder::query()->whereIn('status', ['created', 'pending', 'authorized'])->count()
                    + FolderPaymentOrder::query()->whereIn('status', ['created', 'pending', 'authorized'])->count(),
                'revenuePaise' => (int) Purchase::query()->where('status', 'paid')->sum('amount_paise')
                    + (int) FolderPurchase::query()->where('status', 'paid')->sum('amount_paise'),
            ],
            'recentDesigns' => Design::query()
                ->listed()
                ->with(['owner', 'fileType'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Design $design) => [
                    'id' => $design->public_id,
                    'title' => $design->display_name,
                    'owner' => $design->owner?->name,
                    'fileType' => $design->fileType?->name,
                    'createdAt' => $design->created_at?->toIso8601String(),
                ]),
            'recentPurchases' => $recentDesignPurchases
                ->concat($recentFolderPurchases)
                ->sortByDesc('purchasedAt')
                ->take(5)
                ->values(),
        ]);
    }
}
