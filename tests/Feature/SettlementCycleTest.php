<?php

namespace Tests\Feature;

use App\Models\SaleEarning;
use App\Models\SellerLedgerEntry;
use App\Models\SettlementCycle;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\SellerEarningReleaseService;
use App\Services\SettlementScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SettlementCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_maps_sales_to_same_or_next_month_and_clamps_short_months(): void
    {
        SettlementCycle::query()->create(['start_day' => 1, 'end_day' => 10, 'settlement_day' => 12, 'sort_order' => 1]);
        SettlementCycle::query()->create(['start_day' => 11, 'end_day' => 31, 'settlement_day' => 31, 'sort_order' => 2]);
        $service = app(SettlementScheduleService::class);

        $sameMonth = $service->scheduleFor(Carbon::parse('2026-11-04 10:00:00', 'Asia/Kolkata')->utc());
        $nextMonth = $service->scheduleFor(Carbon::parse('2026-11-20 10:00:00', 'Asia/Kolkata')->utc());

        $this->assertSame('2026-11-12 00:00:00', $sameMonth['availableAt']->setTimezone('Asia/Kolkata')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-31 00:00:00', $nextMonth['availableAt']->setTimezone('Asia/Kolkata')->format('Y-m-d H:i:s'));
    }

    public function test_automatic_test_settlement_moves_due_earning_to_paid_without_manual_request(): void
    {
        SiteSetting::current()->update(['seller_payouts_enabled' => true]);
        SiteSetting::forgetCache();
        $seller = User::factory()->create(['seller_settlement_mode' => 'automatic']);
        $buyer = User::factory()->create();
        $earning = SaleEarning::query()->create([
            'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'item_type' => 'design', 'item_id' => 9876, 'item_public_id' => 'design_auto_test', 'item_name' => 'Auto Test',
            'currency' => 'INR', 'payment_environment' => 'test', 'taxable_amount_paise' => 10000,
            'buyer_gst_paise' => 0, 'buyer_paid_paise' => 10000, 'commission_basis_points' => 2500,
            'commission_paise' => 2500, 'seller_gross_paise' => 7500, 'commission_gst_paise' => 0,
            'tds_paise' => 0, 'gst_tcs_paise' => 0, 'provider_fee_paise' => 0, 'refund_paise' => 0,
            'seller_net_paise' => 7500, 'status' => 'pending', 'available_at' => now()->subMinute(),
            'calculation_snapshot' => [],
        ]);
        SellerLedgerEntry::query()->create([
            'seller_id' => $seller->id, 'sale_earning_id' => $earning->id,
            'entry_type' => 'sale_earning_pending', 'balance_bucket' => 'pending', 'amount_paise' => 7500,
            'currency' => 'INR', 'payment_environment' => 'test', 'idempotency_key' => 'auto-test-pending',
        ]);

        $this->assertSame(1, app(SellerEarningReleaseService::class)->releaseEligible('test'));
        $this->assertDatabaseHas('withdrawals', [
            'seller_id' => $seller->id, 'request_source' => 'automatic', 'status' => 'paid', 'amount_paise' => 7500,
        ]);
        $this->assertSame(7500, (int) SellerLedgerEntry::query()->where('seller_id', $seller->id)->where('balance_bucket', 'paid')->sum('amount_paise'));
        $this->assertSame(0, (int) SellerLedgerEntry::query()->where('seller_id', $seller->id)->where('balance_bucket', 'available')->sum('amount_paise'));
    }
}
