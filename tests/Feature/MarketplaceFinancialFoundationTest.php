<?php

namespace Tests\Feature;

use App\Models\Design;
use App\Models\Folder;
use App\Models\FolderPaymentOrder;
use App\Models\FolderPurchase;
use App\Models\PaymentOrder;
use App\Models\Purchase;
use App\Models\SaleEarning;
use App\Models\SellerLedgerEntry;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\CommissionCalculator;
use App\Services\PurchaseAccessService;
use App\Services\SaleEarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceFinancialFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_calculator_splits_one_hundred_rupees_as_twenty_five_and_seventy_five(): void
    {
        $breakdown = app(CommissionCalculator::class)->calculate(
            taxableAmountPaise: 10000,
            buyerPaidPaise: 10000,
            commissionBasisPoints: 2500,
        );

        $this->assertSame(2500, $breakdown['commission_paise']);
        $this->assertSame(7500, $breakdown['seller_gross_paise']);
        $this->assertSame(7500, $breakdown['seller_net_paise']);
    }

    public function test_paid_design_creates_one_idempotent_pending_earning_and_ledger_credit(): void
    {
        SiteSetting::current()->update([
            'platform_commission_basis_points' => 2500,
            'earning_hold_days' => 7,
        ]);
        SiteSetting::forgetCache();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $design = Design::query()->create([
            'user_id' => $seller->id,
            'title' => 'Marketplace Design',
            'price_paise' => 10000,
            'size' => '6 x 6',
            'color' => 'Gold',
        ]);
        $order = PaymentOrder::query()->create([
            'buyer_id' => $buyer->id,
            'design_id' => $design->id,
            'razorpay_order_id' => 'order_foundation_test',
            'amount_paise' => 10000,
            'currency' => 'INR',
            'payment_environment' => 'test',
            'commission_basis_points' => 2500,
            'status' => 'paid',
        ]);
        $purchase = Purchase::query()->create([
            'buyer_id' => $buyer->id,
            'design_id' => $design->id,
            'payment_order_id' => $order->id,
            'razorpay_payment_id' => 'pay_foundation_test',
            'amount_paise' => 10000,
            'currency' => 'INR',
            'payment_environment' => 'test',
            'status' => 'paid',
            'purchased_at' => now(),
        ]);

        $first = app(SaleEarningService::class)->createForDesignPurchase($purchase, $design);
        $second = app(SaleEarningService::class)->createForDesignPurchase($purchase, $design);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, SaleEarning::query()->count());
        $this->assertSame(1, SellerLedgerEntry::query()->count());
        $this->assertSame($seller->id, $first->seller_id);
        $this->assertSame($buyer->id, $first->buyer_id);
        $this->assertSame(2500, $first->commission_paise);
        $this->assertSame(7500, $first->seller_net_paise);
        $this->assertSame('pending', $first->status);
        $this->assertSame('test', $first->payment_environment);
        $this->assertFalse($first->available_at->isPast());

        $ledger = SellerLedgerEntry::query()->firstOrFail();
        $this->assertSame('pending', $ledger->balance_bucket);
        $this->assertSame(7500, $ledger->amount_paise);
        $this->assertSame('test', $ledger->payment_environment);
    }

    public function test_paid_seller_owned_folder_creates_one_idempotent_earning(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $folder = Folder::query()->create([
            'user_id' => $seller->id,
            'name' => 'Seller Pack',
            'price_paise' => 20000,
            'zip_name' => 'seller-pack.zip',
            'zip_path' => 'folders/seller-pack/source.zip',
        ]);
        $order = FolderPaymentOrder::query()->create([
            'buyer_id' => $buyer->id,
            'folder_id' => $folder->id,
            'razorpay_order_id' => 'order_folder_foundation_test',
            'amount_paise' => 20000,
            'currency' => 'INR',
            'payment_environment' => 'test',
            'commission_basis_points' => 2500,
            'status' => 'paid',
        ]);
        $purchase = FolderPurchase::query()->create([
            'buyer_id' => $buyer->id,
            'folder_id' => $folder->id,
            'folder_payment_order_id' => $order->id,
            'razorpay_payment_id' => 'pay_folder_foundation_test',
            'amount_paise' => 20000,
            'currency' => 'INR',
            'payment_environment' => 'test',
            'status' => 'paid',
            'purchased_at' => now(),
        ]);

        $first = app(SaleEarningService::class)->createForFolderPurchase($purchase, $folder);
        $second = app(SaleEarningService::class)->createForFolderPurchase($purchase, $folder);

        $this->assertNotNull($first);
        $this->assertTrue($first->is($second));
        $this->assertSame(5000, $first->commission_paise);
        $this->assertSame(15000, $first->seller_net_paise);
        $this->assertDatabaseCount('sale_earnings', 1);
        $this->assertDatabaseCount('seller_ledger_entries', 1);
    }

    public function test_test_purchase_does_not_grant_live_download_access(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $design = Design::query()->create([
            'user_id' => $seller->id,
            'title' => 'Environment Design',
            'price_paise' => 10000,
            'size' => '6 x 6',
            'color' => 'Blue',
        ]);
        Purchase::query()->create([
            'buyer_id' => $buyer->id,
            'design_id' => $design->id,
            'razorpay_payment_id' => 'pay_test_environment',
            'amount_paise' => 10000,
            'currency' => 'INR',
            'payment_environment' => 'test',
            'status' => 'paid',
            'purchased_at' => now(),
        ]);

        config(['designhub.payment_environment' => 'live']);

        $this->assertFalse(app(PurchaseAccessService::class)->canDownloadStitch($buyer, $design));

        config(['designhub.payment_environment' => 'test']);

        $this->assertTrue(app(PurchaseAccessService::class)->canDownloadStitch($buyer, $design));
    }
}
