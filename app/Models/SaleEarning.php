<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SaleEarning extends Model
{
    protected $fillable = [
        'public_id',
        'seller_id',
        'buyer_id',
        'design_purchase_id',
        'folder_purchase_id',
        'catalog_purchase_id',
        'item_type',
        'item_id',
        'item_public_id',
        'item_name',
        'currency',
        'payment_environment',
        'taxable_amount_paise',
        'buyer_gst_paise',
        'buyer_gst_basis_points',
        'cgst_paise',
        'sgst_paise',
        'igst_paise',
        'gst_supply_type',
        'seller_state_code',
        'buyer_state_code',
        'buyer_paid_paise',
        'commission_basis_points',
        'commission_paise',
        'seller_gross_paise',
        'commission_gst_paise',
        'commission_gst_basis_points',
        'tds_paise',
        'gst_tcs_paise',
        'provider_fee_paise',
        'refund_paise',
        'seller_net_paise',
        'status',
        'available_at',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'taxable_amount_paise' => 'integer',
            'buyer_gst_paise' => 'integer',
            'buyer_gst_basis_points' => 'integer',
            'cgst_paise' => 'integer',
            'sgst_paise' => 'integer',
            'igst_paise' => 'integer',
            'buyer_paid_paise' => 'integer',
            'commission_basis_points' => 'integer',
            'commission_paise' => 'integer',
            'seller_gross_paise' => 'integer',
            'commission_gst_paise' => 'integer',
            'commission_gst_basis_points' => 'integer',
            'tds_paise' => 'integer',
            'gst_tcs_paise' => 'integer',
            'provider_fee_paise' => 'integer',
            'refund_paise' => 'integer',
            'seller_net_paise' => 'integer',
            'available_at' => 'datetime',
            'calculation_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SaleEarning $earning) {
            if (! $earning->public_id) {
                $earning->public_id = 'earning_'.Str::lower(Str::random(12));
            }
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function designPurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'design_purchase_id');
    }

    public function folderPurchase(): BelongsTo
    {
        return $this->belongsTo(FolderPurchase::class, 'folder_purchase_id');
    }

    public function catalogPurchase(): BelongsTo
    {
        return $this->belongsTo(CatalogPurchase::class, 'catalog_purchase_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SellerLedgerEntry::class);
    }
}
