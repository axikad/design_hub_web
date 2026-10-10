<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'public_id',
        'role_id',
        'name',
        'country_code',
        'mobile_number',
        'phone_key',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (! $user->public_id) {
                $user->public_id = 'user_'.Str::lower(Str::random(12));
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function designs(): HasMany
    {
        return $this->hasMany(Design::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    public function savedDesigns(): HasMany
    {
        return $this->hasMany(SavedDesign::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'buyer_id');
    }

    public function isSuperAdmin(): bool
    {
        $this->loadMissing('role');

        return $this->role?->isSuperAdmin() === true;
    }

    public function hasPermission(string $slug, string $action): bool
    {
        if ($this->isSuperAdmin() || $this->is_admin) {
            return true;
        }

        $this->loadMissing('role');

        return $this->role?->hasPermission($slug, $action) ?? false;
    }

    public function canAccessPanel(): bool
    {
        return $this->is_admin || $this->hasPermission('admin_access', 'read');
    }

    public function ownerKey(): ?string
    {
        if ($this->country_code === null || $this->mobile_number === null) {
            return null;
        }

        return $this->country_code.$this->mobile_number;
    }

    public function toMobileArray(): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'countryCode' => $this->country_code,
            'mobileNumber' => $this->mobile_number,
        ];
    }

    /** @return array<string, mixed> */
    public function toAdminArray(): array
    {
        $this->loadMissing('role');

        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'countryCode' => $this->country_code ?? '+91',
            'mobileNumber' => $this->mobile_number ?? '',
            'roleId' => $this->role_id,
            'roleName' => $this->role?->name,
            'roleSlug' => $this->role?->slug,
        ];
    }
}
