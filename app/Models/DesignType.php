<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DesignType extends Model
{
    protected $fillable = [
        'public_id',
        'design_category_id',
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
        static::creating(function (DesignType $type) {
            if (! $type->public_id) {
                $type->public_id = 'type_'.Str::lower(Str::random(10));
            }
        });

        static::saved(function (DesignType $type) {
            if ($type->design_category_id) {
                $type->categories()->syncWithoutDetaching([$type->design_category_id]);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DesignCategory::class, 'design_category_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(DesignCategory::class, 'category_design_type')
            ->withPivot('sort_order');
    }

    public function designs(): HasMany
    {
        return $this->hasMany(Design::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(DesignVariant::class);
    }
}
