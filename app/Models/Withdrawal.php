<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Withdrawal extends Model
{
    public const OPEN_STATUSES = ['requested', 'approved', 'processing'];

    protected $fillable = [
        'public_id',
        'seller_id',
        'amount_paise',
        'fee_paise',
        'net_transfer_paise',
        'currency',
        'payment_environment',
        'payout_mode',
        'request_source',
        'status',
        'idempotency_key',
        'requested_at',
        'cancelled_at',
        'approved_at',
        'processed_at',
        'approved_by',
        'provider',
        'provider_transfer_id',
        'provider_status',
        'failure_code',
        'failure_message',
        'rejection_reason',
        'payout_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'fee_paise' => 'integer',
            'net_transfer_paise' => 'integer',
            'requested_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
            'payout_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Withdrawal $withdrawal) {
            if (! $withdrawal->public_id) {
                $withdrawal->public_id = 'withdrawal_'.Str::lower(Str::random(12));
            }
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WithdrawalStatusHistory::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SellerLedgerEntry::class);
    }
}
