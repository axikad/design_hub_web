<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentOrder extends Model
{
    protected $fillable = [
        'public_id',
        'buyer_id',
        'design_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'amount_paise',
        'currency',
        'status',
        'last_reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'last_reconciled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentOrder $order) {
            if (! $order->public_id) {
                $order->public_id = 'order_'.Str::lower(Str::random(12));
            }
        });
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
