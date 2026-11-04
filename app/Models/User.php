<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

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
        'allow_otp_login',
        'allow_password_login',
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
            'allow_otp_login' => 'boolean',
            'allow_password_login' => 'boolean',
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

    public function folderPurchases(): HasMany
    {
        return $this->hasMany(FolderPurchase::class, 'buyer_id');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function saleEarnings(): HasMany
    {
        return $this->hasMany(SaleEarning::class, 'seller_id');
    }

    public function sellerLedgerEntries(): HasMany
    {
        return $this->hasMany(SellerLedgerEntry::class, 'seller_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class, 'seller_id');
    }

    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(Machine::class)
            ->withTimestamps()
            ->orderBy('machines.sort_order')
            ->orderBy('machines.name');
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

    public function mustScopeMarketplaceContentToSelf(): bool
    {
        if ($this->isSuperAdmin()) {
            return false;
        }

        $this->loadMissing('role');

        return $this->role?->hasPermission('marketplace.manage_own') ?? false;
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
        $this->loadMissing('role.permissions', 'machines');

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
            'email' => $this->email,
            'avatarUri' => $this->avatar_path === null
                ? null
                : Storage::disk('public')->url($this->avatar_path),
            'countryCode' => $this->country_code,
            'mobileNumber' => $this->mobile_number,
            'role' => $this->role === null ? null : [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ],
            'permissions' => $permissions,
            'machineFilteringActive' => $this->machines->isNotEmpty(),
            'selectedMachineIds' => $this->machines->pluck('public_id')->values()->all(),
            'selectedMachines' => $this->machines
                ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
                ->map(fn (Machine $machine) => [
                    'id' => $machine->public_id,
                    'name' => $machine->name,
                    'imageUri' => $machine->imageUrl(),
                ])
                ->values()
                ->all(),
            'loginMethods' => [
                'otp' => (bool) $this->allow_otp_login,
                'password' => (bool) $this->allow_password_login,
            ],
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
            'allowOtpLogin' => (bool) $this->allow_otp_login,
            'allowPasswordLogin' => (bool) $this->allow_password_login,
            'hasPassword' => $this->password !== null,
        ];
    }
}
