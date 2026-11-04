<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class SellerLedgerEntry extends Model
{
    protected $fillable = [
        'public_id',
        'seller_id',
        'sale_earning_id',
        'withdrawal_id',
        'entry_type',
        'balance_bucket',
        'amount_paise',
        'currency',
        'payment_environment',
        'idempotency_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SellerLedgerEntry $entry) {
            if (! $entry->public_id) {
                $entry->public_id = 'ledger_'.Str::lower(Str::random(12));
            }
        });

        static::updating(fn () => throw new LogicException('Seller ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Seller ledger entries are immutable.'));
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function saleEarning(): BelongsTo
    {
        return $this->belongsTo(SaleEarning::class);
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }
}
