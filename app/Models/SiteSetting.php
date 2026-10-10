<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $fillable = [
        'name',
        'logo_path',
        'favicon_path',
    ];

    public static function current(): self
    {
        return Cache::remember('site_settings.current', 3600, function () {
            $existing = static::query()->oldest('id')->first();

            if ($existing) {
                return $existing;
            }

            return static::query()->create([
                'name' => 'DesignHub Admin',
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
