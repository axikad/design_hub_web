<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CatalogPaymentOrder extends Model
{
    protected $fillable = [
        'public_id',
        'buyer_id',
        'catalog_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'amount_paise',
        'taxable_amount_paise',
        'buyer_gst_basis_points',
        'buyer_gst_paise',
        'cgst_paise',
        'sgst_paise',
        'igst_paise',
        'gst_supply_type',
        'seller_state_code',
        'buyer_state_code',
        'currency',
        'payment_environment',
        'commission_basis_points',
        'commission_gst_basis_points',
        'commission_gst_paise',
        'tax_snapshot',
        'status',
        'last_reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'taxable_amount_paise' => 'integer',
            'buyer_gst_basis_points' => 'integer',
            'buyer_gst_paise' => 'integer',
            'cgst_paise' => 'integer',
            'sgst_paise' => 'integer',
            'igst_paise' => 'integer',
            'commission_basis_points' => 'integer',
            'commission_gst_basis_points' => 'integer',
            'commission_gst_paise' => 'integer',
            'tax_snapshot' => 'array',
            'last_reconciled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CatalogPaymentOrder $order) {
            if (! $order->public_id) {
                $order->public_id = 'catalog_order_'.Str::lower(Str::random(12));
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
}
