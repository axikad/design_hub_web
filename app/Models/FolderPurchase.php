<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FolderPurchase extends Model
{
    protected $fillable = [
        'public_id',
        'buyer_id',
        'folder_id',
        'folder_payment_order_id',
        'razorpay_payment_id',
        'amount_paise',
        'currency',
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
        static::creating(function (FolderPurchase $purchase) {
            if (! $purchase->public_id) {
                $purchase->public_id = 'folder_purchase_'.Str::lower(Str::random(12));
            }
        });
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(FolderPaymentOrder::class, 'folder_payment_order_id');
    }
}
