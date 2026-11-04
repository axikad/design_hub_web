<?php

namespace Tests\Feature;

use App\Models\SaleEarning;
use App\Models\SellerLedgerEntry;
use App\Models\User;
use App\Services\SellerEarningReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerEarningReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_earning_moves_from_pending_to_available_exactly_once(): void
    {
        $seller = User::factory()->create();
        $earning = $this->createEarning($seller, now()->subMinute());
        $this->createPendingCredit($earning);

        $service = app(SellerEarningReleaseService::class);

        $this->assertSame(1, $service->releaseEligible('test'));
        $this->assertSame(0, $service->releaseEligible('test'));
        $this->assertSame('available', $earning->fresh()->status);
        $this->assertDatabaseCount('seller_ledger_entries', 3);

        $pending = SellerLedgerEntry::query()
            ->where('seller_id', $seller->id)
            ->where('balance_bucket', 'pending')
            ->sum('amount_paise');
        $available = SellerLedgerEntry::query()
            ->where('seller_id', $seller->id)
            ->where('balance_bucket', 'available')
            ->sum('amount_paise');

        $this->assertSame(0, $pending);
        $this->assertSame(7500, $available);
        $this->assertDatabaseHas('seller_ledger_entries', [
            'sale_earning_id' => $earning->id,
            'entry_type' => 'earning_release_pending_debit',
            'amount_paise' => -7500,
        ]);
        $this->assertDatabaseHas('seller_ledger_entries', [
            'sale_earning_id' => $earning->id,
            'entry_type' => 'earning_release_available_credit',
            'amount_paise' => 7500,
        ]);
    }

    public function test_future_and_other_environment_earnings_are_not_released(): void
    {
        $seller = User::factory()->create();
        $future = $this->createEarning($seller, now()->addDay());
        $live = $this->createEarning($seller, now()->subMinute(), 'live');
        $this->createPendingCredit($future);
        $this->createPendingCredit($live);

        $this->assertSame(0, app(SellerEarningReleaseService::class)->releaseEligible('test'));
        $this->assertSame('pending', $future->fresh()->status);
        $this->assertSame('pending', $live->fresh()->status);
        $this->assertDatabaseCount('seller_ledger_entries', 2);
    }

    public function test_release_command_processes_the_selected_environment(): void
    {
        $seller = User::factory()->create();
        $earning = $this->createEarning($seller, now()->subMinute());
        $this->createPendingCredit($earning);

        $this->artisan('marketplace:release-earnings', ['--environment' => 'test'])
            ->expectsOutput('Released 1 test seller earning(s).')
            ->assertSuccessful();

        $this->assertSame('available', $earning->fresh()->status);
    }

    private function createEarning(User $seller, mixed $availableAt, string $environment = 'test'): SaleEarning
    {
        $buyer = User::factory()->create();

        return SaleEarning::query()->create([
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'item_type' => 'design',
            'item_id' => fake()->unique()->numberBetween(1, 100000),
            'item_public_id' => 'design_'.fake()->unique()->numerify('########'),
            'item_name' => 'Release Test Design',
            'currency' => 'INR',
            'payment_environment' => $environment,
            'taxable_amount_paise' => 10000,
            'buyer_gst_paise' => 0,
            'buyer_paid_paise' => 10000,
            'commission_basis_points' => 2500,
            'commission_paise' => 2500,
            'seller_gross_paise' => 7500,
            'commission_gst_paise' => 0,
            'tds_paise' => 0,
            'gst_tcs_paise' => 0,
            'provider_fee_paise' => 0,
            'refund_paise' => 0,
            'seller_net_paise' => 7500,
            'status' => 'pending',
            'available_at' => $availableAt,
            'calculation_snapshot' => [],
        ]);
    }

    private function createPendingCredit(SaleEarning $earning): SellerLedgerEntry
    {
        return SellerLedgerEntry::query()->create([
            'seller_id' => $earning->seller_id,
            'sale_earning_id' => $earning->id,
            'entry_type' => 'sale_earning_pending',
            'balance_bucket' => 'pending',
            'amount_paise' => $earning->seller_net_paise,
            'currency' => 'INR',
            'payment_environment' => $earning->payment_environment,
            'idempotency_key' => 'test:pending:'.$earning->public_id,
        ]);
    }
}
