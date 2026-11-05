<?php

namespace App\Services;

use App\Models\SettlementCycle;
use Illuminate\Support\Carbon;

class SettlementScheduleService
{
    /** @return array{availableAt: Carbon, snapshot: array<string, mixed>} */
    public function scheduleFor(Carbon $earnedAt): array
    {
        $timezone = (string) config('designhub.marketplace_timezone', 'Asia/Kolkata');
        $localEarnedAt = $earnedAt->copy()->setTimezone($timezone);
        $cycle = SettlementCycle::query()
            ->where('start_day', '<=', $localEarnedAt->day)
            ->where('end_day', '>=', $localEarnedAt->day)
            ->orderBy('sort_order')
            ->first();

        if ($cycle === null) {
            $days = app(MarketplacePaymentSettings::class)->earningHoldDays();

            return [
                'availableAt' => $earnedAt->copy()->addDays($days),
                'snapshot' => ['type' => 'legacy_hold', 'earning_hold_days' => $days],
            ];
        }

        $target = $localEarnedAt->copy()->startOfMonth();
        if ($cycle->settlement_day <= $cycle->end_day) {
            $target->addMonthNoOverflow();
        }
        $day = min($cycle->settlement_day, $target->daysInMonth);
        $availableAt = $target->day($day)->startOfDay()->utc();

        return [
            'availableAt' => $availableAt,
            'snapshot' => [
                'type' => 'monthly_cycle',
                'start_day' => $cycle->start_day,
                'end_day' => $cycle->end_day,
                'settlement_day' => $cycle->settlement_day,
                'timezone' => $timezone,
                'settlement_at' => $availableAt->toIso8601String(),
            ],
        ];
    }
}
