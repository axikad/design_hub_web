<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class CatalogPurchase extends Model
{
    protected $fillable = [
        'public_id',
        'buyer_id',
        'catalog_id',
        'catalog_payment_order_id',
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
        static::creating(function (CatalogPurchase $purchase) {
            if (! $purchase->public_id) {
                $purchase->public_id = 'catalog_purchase_'.Str::lower(Str::random(12));
            }
        });
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(CatalogPaymentOrder::class, 'catalog_payment_order_id');
    }

    public function saleEarning(): HasOne
    {
        return $this->hasOne(SaleEarning::class, 'catalog_purchase_id');
    }
}
