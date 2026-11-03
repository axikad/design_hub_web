<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $fillable = [
        'name',
        'logo_path',
        'favicon_path',
        'default_registration_role_id',
        'default_mobile_registration_role_id',
        'image_search_similarity',
        'duplicate_image_similarity',
        'seller_payouts_enabled',
        'platform_commission_basis_points',
        'earning_hold_days',
        'minimum_withdrawal_paise',
        'buyer_gst_enabled',
        'buyer_gst_basis_points',
        'commission_gst_enabled',
        'commission_gst_basis_points',
        'tds_enabled',
        'tds_basis_points',
        'gst_tcs_enabled',
        'gst_tcs_basis_points',
    ];

    protected function casts(): array
    {
        return [
            'image_search_similarity' => 'integer',
            'duplicate_image_similarity' => 'integer',
            'seller_payouts_enabled' => 'boolean',
            'platform_commission_basis_points' => 'integer',
            'earning_hold_days' => 'integer',
            'minimum_withdrawal_paise' => 'integer',
            'buyer_gst_enabled' => 'boolean',
            'buyer_gst_basis_points' => 'integer',
            'commission_gst_enabled' => 'boolean',
            'commission_gst_basis_points' => 'integer',
            'tds_enabled' => 'boolean',
            'tds_basis_points' => 'integer',
            'gst_tcs_enabled' => 'boolean',
            'gst_tcs_basis_points' => 'integer',
        ];
    }

    public static function current(): self
    {
        return Cache::remember('site_settings.current', 3600, function () {
            $existing = static::query()->oldest('id')->first();

            if ($existing) {
                return $existing;
            }

            return static::query()->create([
                'name' => 'Textile Design Hub',
            ]);
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget('site_settings.current');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? '/storage/'.$this->logo_path : null;
    }

    public function faviconUrl(): ?string
    {
        return $this->favicon_path ? '/storage/'.$this->favicon_path : null;
    }

    public function defaultRegistrationRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'default_registration_role_id');
    }

    public function defaultMobileRegistrationRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'default_mobile_registration_role_id');
    }

    /** @return array{name: string, logoUrl: string|null, faviconUrl: string|null} */
    public function toBranding(): array
    {
        return [
            'name' => $this->name,
            'logoUrl' => $this->logoUrl(),
            'faviconUrl' => $this->faviconUrl(),
        ];
    }
}
