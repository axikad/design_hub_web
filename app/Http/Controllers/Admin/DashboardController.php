<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\FolderPaymentOrder;
use App\Models\FolderPurchase;
use App\Models\PaymentOrder;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $actor = $request->user();
        $ownContentOnly = $actor->mustScopeMarketplaceContentToSelf();

        $recentDesignPurchases = Purchase::query()
            ->with(['buyer', 'design'])
            ->when($ownContentOnly, fn ($query) => $query->whereHas('design', fn ($designQuery) => $designQuery->where('user_id', $actor->id)))
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
            ->when($ownContentOnly, fn ($query) => $query->whereHas('folder', fn ($folderQuery) => $folderQuery->where('user_id', $actor->id)))
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

        $designs = Design::query()->listed()
            ->when($ownContentOnly, fn ($query) => $query->where('user_id', $actor->id));
        $designPurchases = Purchase::query()
            ->when($ownContentOnly, fn ($query) => $query->whereHas('design', fn ($designQuery) => $designQuery->where('user_id', $actor->id)));
        $folderPurchases = FolderPurchase::query()
            ->when($ownContentOnly, fn ($query) => $query->whereHas('folder', fn ($folderQuery) => $folderQuery->where('user_id', $actor->id)));

        return Inertia::render('admin/dashboard', [
            'ownContentOnly' => $ownContentOnly,
            'stats' => [
                'users' => $ownContentOnly ? 0 : User::query()->whereNotNull('phone_key')->count(),
                'designs' => (clone $designs)->count(),
                'folders' => $ownContentOnly ? $actor->folders()->count() : 0,
                'purchases' => (clone $designPurchases)->where('status', 'paid')->count()
                    + (clone $folderPurchases)->where('status', 'paid')->count(),
                'pendingPayments' => $ownContentOnly
                    ? (clone $designPurchases)->whereIn('status', ['unpaid', 'pending'])->count()
                        + (clone $folderPurchases)->whereIn('status', ['unpaid', 'pending'])->count()
                    : PaymentOrder::query()->whereIn('status', ['created', 'pending', 'authorized'])->count()
                        + FolderPaymentOrder::query()->whereIn('status', ['created', 'pending', 'authorized'])->count(),
                'revenuePaise' => (int) (clone $designPurchases)->where('status', 'paid')->sum('amount_paise')
                    + (int) (clone $folderPurchases)->where('status', 'paid')->sum('amount_paise'),
            ],
            'recentDesigns' => Design::query()
                ->listed()
                ->when($ownContentOnly, fn ($query) => $query->where('user_id', $actor->id))
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
