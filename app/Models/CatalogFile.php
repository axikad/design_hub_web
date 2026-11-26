<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CatalogFile extends Model
{
    protected $fillable = [
        'public_id',
        'name',
        'relative_path',
        'storage_path',
        'extension',
        'mime_type',
        'size',
    ];

    protected static function booted(): void
    {
        static::creating(function (CatalogFile $file) {
            if (! $file->public_id) {
                $file->public_id = 'catalog_file_'.Str::lower(Str::random(12));
            }
        });
    }

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }
}
