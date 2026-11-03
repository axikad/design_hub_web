<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Purchase extends Model
{
    protected $fillable = [
        'public_id',
        'buyer_id',
        'design_id',
        'payment_order_id',
        'razorpay_payment_id',
        'amount_paise',
        'currency',
        'payment_environment',
        'status',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'purchased_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Purchase $purchase) {
            if (! $purchase->public_id) {
                $purchase->public_id = 'purchase_'.Str::lower(Str::random(12));
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

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function saleEarning(): HasOne
    {
        return $this->hasOne(SaleEarning::class, 'design_purchase_id');
    }
}
