<?php

namespace App\Services;

use App\Models\SellerLedgerEntry;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerWithdrawalService
{
    public function __construct(private readonly MarketplacePaymentSettings $settings) {}

    /** @return array{withdrawal: Withdrawal, created: bool} */
    public function create(User $seller, int $amountPaise, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($seller, $amountPaise, $idempotencyKey) {
            User::query()->whereKey($seller->id)->lockForUpdate()->firstOrFail();

            $environment = $this->settings->environment();
            $existing = Withdrawal::query()
                ->where('seller_id', $seller->id)
                ->where('payment_environment', $environment)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                if ($existing->amount_paise !== $amountPaise) {
                    throw ValidationException::withMessages([
                        'idempotencyKey' => ['This idempotency key was already used with a different amount.'],
                    ]);
                }

                return ['withdrawal' => $existing, 'created' => false];
            }

            if (! $this->settings->sellerPayoutsEnabled()) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['Seller withdrawal requests are currently disabled.'],
                ]);
            }

            $minimum = $this->settings->minimumWithdrawalPaise();
            if ($amountPaise < $minimum) {
                throw ValidationException::withMessages([
                    'amountPaise' => ["The minimum withdrawal amount is {$minimum} paise."],
                ]);
            }

            $hasOpenRequest = Withdrawal::query()
                ->where('seller_id', $seller->id)
                ->where('payment_environment', $environment)
                ->whereIn('status', Withdrawal::OPEN_STATUSES)
                ->exists();

            if ($hasOpenRequest) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['Complete or cancel the existing withdrawal request before creating another one.'],
                ]);
            }

            $entries = SellerLedgerEntry::query()
                ->where('seller_id', $seller->id)
                ->where('payment_environment', $environment)
                ->lockForUpdate()
                ->get(['balance_bucket', 'amount_paise']);
            $available = (int) $entries
                ->where('balance_bucket', 'available')
                ->sum('amount_paise');

            if ($amountPaise > $available) {
                throw ValidationException::withMessages([
                    'amountPaise' => ['The withdrawal amount exceeds the available balance.'],
                ]);
            }

            $withdrawal = Withdrawal::query()->create([
                'seller_id' => $seller->id,
                'amount_paise' => $amountPaise,
                'fee_paise' => 0,
                'net_transfer_paise' => $amountPaise,
                'currency' => 'INR',
                'payment_environment' => $environment,
                'payout_mode' => $environment === 'test' ? 'simulated' : 'manual',
                'status' => 'requested',
                'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
            ]);

            $this->ledger($withdrawal, 'withdrawal_reserve_available_debit', 'available', -$amountPaise);
            $this->ledger($withdrawal, 'withdrawal_reserve_reserved_credit', 'reserved', $amountPaise);
            $this->history($withdrawal, null, 'requested', $seller, 'Withdrawal requested by seller.');

            return ['withdrawal' => $withdrawal, 'created' => true];
        }, 3);
    }

    public function cancel(Withdrawal $withdrawal, User $seller): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $seller) {
            User::query()->whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($locked->seller_id !== $seller->id
                || $locked->payment_environment !== $this->settings->environment()) {
                abort(404);
            }

            if ($locked->status === 'cancelled') {
                return $locked;
            }

            if ($locked->status !== 'requested') {
                throw ValidationException::withMessages([
                    'withdrawal' => ['Only a requested withdrawal can be cancelled.'],
                ]);
            }

            $this->ledger($locked, 'withdrawal_cancel_reserved_debit', 'reserved', -$locked->amount_paise);
            $this->ledger($locked, 'withdrawal_cancel_available_credit', 'available', $locked->amount_paise);

            $fromStatus = $locked->status;
            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
            $this->history($locked, $fromStatus, 'cancelled', $seller, 'Withdrawal cancelled by seller.');

            return $locked->fresh();
        }, 3);
    }

    private function ledger(Withdrawal $withdrawal, string $type, string $bucket, int $amount): void
    {
        SellerLedgerEntry::query()->firstOrCreate(
            ['idempotency_key' => "withdrawal:{$withdrawal->id}:{$type}"],
            [
                'seller_id' => $withdrawal->seller_id,
                'withdrawal_id' => $withdrawal->id,
                'entry_type' => $type,
                'balance_bucket' => $bucket,
                'amount_paise' => $amount,
                'currency' => $withdrawal->currency,
                'payment_environment' => $withdrawal->payment_environment,
                'metadata' => ['withdrawalId' => $withdrawal->public_id],
            ],
        );
    }

    private function history(
        Withdrawal $withdrawal,
        ?string $fromStatus,
        string $toStatus,
        User $actor,
        string $note,
    ): void {
        WithdrawalStatusHistory::query()->create([
            'withdrawal_id' => $withdrawal->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => $actor->id,
            'note' => $note,
        ]);
    }
}
