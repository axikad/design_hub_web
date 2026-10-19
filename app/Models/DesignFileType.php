<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DesignFileType extends Model
{
    protected $fillable = [
        'public_id',
        'name',
        'extension',
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
        static::creating(function (DesignFileType $fileType) {
            if (! $fileType->public_id) {
                $fileType->public_id = Str::slug($fileType->name);
            }

            $fileType->extension = Str::lower(ltrim($fileType->extension, '.'));
        });

        static::updating(function (DesignFileType $fileType) {
            $fileType->extension = Str::lower(ltrim($fileType->extension, '.'));
        });
    }

    public function designs(): HasMany
    {
        return $this->hasMany(Design::class);
    }
}
