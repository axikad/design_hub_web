<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Design extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'design_type_id',
        'machine_type_id',
        'category_id',
        'show_price',
        'details',
        'images',
        'files',
        'title',
        'price_paise',
        'size',
        'color',
        'preview_path',
        'stitch_path',
        'file_name',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'price_paise' => 'integer',
            'show_price' => 'boolean',
            'details' => 'array',
            'images' => 'array',
            'files' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Design $design) {
            if (! $design->public_id) {
                $design->public_id = 'design_'.Str::lower(Str::random(12));
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DesignType::class, 'design_type_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function scopeListed($query)
    {
        return $query->whereNull('deleted_at');
    }
}
