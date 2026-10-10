<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Role extends Model
{
    protected $fillable = [
        'slug',
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissionAssignments(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === 'super_admin';
    }

    public function hasPermission(string $slug, string $action): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! in_array($action, ['read', 'create', 'update', 'delete'], true)) {
            return false;
        }

        return $this->permissionAssignments()
            ->where($action, true)
            ->whereHas('permission', fn ($query) => $query->where('slug', $slug))
            ->exists();
    }

    public static function slugFromName(string $name): string
    {
        return Str::slug($name, '_');
    }
}
