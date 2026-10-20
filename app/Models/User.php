<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $attributes = [
        'status' => 'active',
    ];

    protected $fillable = [
        'public_id',
        'role_id',
        'name',
        'country_code',
        'mobile_number',
        'phone_key',
        'email',
        'avatar_path',
        'password',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
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

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->loadMissing('role');

        return $this->role?->hasPermission($permission) ?? false;
    }

    public function canAccessPanel(): bool
    {
        return $this->hasPermission('dashboard.read');
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
        $this->loadMissing('role.permissions');

        $permissions = $this->isSuperAdmin()
            ? Permission::query()->where('is_active', true)->pluck('slug')->values()->all()
            : ($this->role?->permissions
                ->where('is_active', true)
                ->pluck('slug')
                ->values()
                ->all() ?? []);

        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'countryCode' => $this->country_code,
            'mobileNumber' => $this->mobile_number,
            'role' => $this->role === null ? null : [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ],
            'permissions' => $permissions,
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
            'avatarPath' => $this->avatar_path,
            'status' => $this->status,
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
            'countryCode' => $this->country_code ?? '+91',
            'mobileNumber' => $this->mobile_number ?? '',
            'roleId' => $this->role_id,
            'roleName' => $this->role?->name,
            'roleSlug' => $this->role?->slug,
        ];
    }
}
