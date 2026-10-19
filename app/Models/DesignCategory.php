<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DesignCategory extends Model
{
    protected $fillable = [
        'public_id',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DesignCategory $category) {
            if (! $category->public_id) {
                $category->public_id = 'cat_'.Str::lower(Str::random(10));
            }
        });
    }

    public function types(): HasMany
    {
        return $this->hasMany(DesignType::class)->orderBy('sort_order')->orderBy('name');
    }

    public function activeTypes(): HasMany
    {
        return $this->types()->where('is_active', true);
    }

    public function machineTypes(): BelongsToMany
    {
        return $this->belongsToMany(MachineType::class, 'category_machine_type');
    }
}
