<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaleEarning;
use App\Models\SettlementCycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettlementEarningController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $cycle = (string) $request->string('cycle', 'all');
        $environment = (string) $request->string('environment');
        $timezone = (string) config('designhub.marketplace_timezone', 'Asia/Kolkata');

        $configuredCycles = SettlementCycle::query()
            ->orderBy('sort_order')
            ->orderBy('start_day')
            ->get()
            ->map(fn (SettlementCycle $settlementCycle) => [
                'key' => $this->cycleKey($settlementCycle->start_day, $settlementCycle->end_day, $settlementCycle->settlement_day),
                'startDay' => $settlementCycle->start_day,
                'endDay' => $settlementCycle->end_day,
                'settlementDay' => $settlementCycle->settlement_day,
            ])
            ->toBase()
            ->keyBy('key');

        $appliedCycles = SaleEarning::query()
            ->get(['calculation_snapshot'])
            ->map(function (SaleEarning $earning): ?array {
                $settlement = data_get($earning->calculation_snapshot, 'settlement', []);
                if (data_get($settlement, 'type') !== 'monthly_cycle') {
                    return null;
                }

                $startDay = (int) data_get($settlement, 'start_day');
                $endDay = (int) data_get($settlement, 'end_day');
                $settlementDay = (int) data_get($settlement, 'settlement_day');
                if ($startDay < 1 || $endDay < $startDay || $settlementDay < 1) {
                    return null;
                }

                return [
                    'key' => $this->cycleKey($startDay, $endDay, $settlementDay),
                    'startDay' => $startDay,
                    'endDay' => $endDay,
                    'settlementDay' => $settlementDay,
                ];
            })
            ->toBase()
            ->filter()
            ->groupBy('key');

        $cycles = $configuredCycles
            ->merge($appliedCycles->map(fn ($records) => $records->first()))
            ->map(fn (array $cycle) => [
                ...$cycle,
                'count' => $appliedCycles->get($cycle['key'])?->count() ?? 0,
            ])
            ->sortBy(fn (array $cycle) => sprintf('%02d-%02d-%02d', $cycle['startDay'], $cycle['endDay'], $cycle['settlementDay']))
            ->values();

        $query = SaleEarning::query()
            ->with('seller:id,name,seller_settlement_mode')
            ->when($search !== '', fn (Builder $earningQuery) => $earningQuery->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('public_id', 'like', "%{$search}%")
                    ->orWhere('item_name', 'like', "%{$search}%")
                    ->orWhereHas('seller', fn (Builder $sellerQuery) => $sellerQuery->where('name', 'like', "%{$search}%"));
            }))
            ->when($status !== '', fn (Builder $earningQuery) => $earningQuery->where('status', $status))
            ->when($environment !== '', fn (Builder $earningQuery) => $earningQuery->where('payment_environment', $environment));

        $this->applyCycleFilter($query, $cycle);

        $earnings = $query
            ->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (SaleEarning $earning) => $this->serializeEarning($earning));

        $totals = SaleEarning::query()
            ->selectRaw('status, COUNT(*) as records, COALESCE(SUM(seller_net_paise), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('admin/settlement-earnings/index', [
            'earnings' => $earnings,
            'filters' => compact('search', 'status', 'cycle', 'environment'),
            'cycles' => $cycles,
            'legacyCount' => $this->legacyQuery()->count(),
            'allCount' => SaleEarning::query()->count(),
            'summary' => [
                'waitingCount' => (int) data_get($totals, 'pending.records', 0),
                'waitingPaise' => (int) data_get($totals, 'pending.amount', 0),
                'releasedCount' => (int) data_get($totals, 'available.records', 0),
                'releasedPaise' => (int) data_get($totals, 'available.amount', 0),
            ],
            'paymentEnvironment' => config('designhub.payment_environment', 'test'),
            'marketplaceTimezone' => $timezone,
            'serverTime' => now()->setTimezone($timezone)->toIso8601String(),
            'statusOptions' => ['pending', 'available', 'reserved', 'paid', 'refunded', 'reversed', 'held'],
        ]);
    }

    private function applyCycleFilter(Builder $query, string $cycle): void
    {
        if ($cycle === 'legacy') {
            $query->where(function (Builder $legacyQuery): void {
                $legacyQuery
                    ->whereNull('calculation_snapshot->settlement->type')
                    ->orWhere('calculation_snapshot->settlement->type', '!=', 'monthly_cycle');
            });

            return;
        }

        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{1,2})$/', $cycle, $matches) !== 1) {
            return;
        }

        $query
            ->where('calculation_snapshot->settlement->type', 'monthly_cycle')
            ->where('calculation_snapshot->settlement->start_day', (int) $matches[1])
            ->where('calculation_snapshot->settlement->end_day', (int) $matches[2])
            ->where('calculation_snapshot->settlement->settlement_day', (int) $matches[3]);
    }

    private function legacyQuery(): Builder
    {
        return SaleEarning::query()->where(function (Builder $query): void {
            $query
                ->whereNull('calculation_snapshot->settlement->type')
                ->orWhere('calculation_snapshot->settlement->type', '!=', 'monthly_cycle');
        });
    }

    /** @return array<string, mixed> */
    private function serializeEarning(SaleEarning $earning): array
    {
        $settlement = data_get($earning->calculation_snapshot, 'settlement', []);

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
                'type' => (string) data_get($settlement, 'type', 'legacy_hold'),
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

    private function cycleKey(int $startDay, int $endDay, int $settlementDay): string
    {
        return "{$startDay}-{$endDay}-{$settlementDay}";
    }
}
