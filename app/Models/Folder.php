<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Folder extends Model
{
    protected $fillable = [
        'public_id',
        'name',
        'price_paise',
        'zip_name',
        'zip_path',
        'file_count',
        'total_size',
    ];

    protected function casts(): array
    {
        return [
            'price_paise' => 'integer',
            'file_count' => 'integer',
            'total_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Folder $folder) {
            if (! $folder->public_id) {
                $folder->public_id = 'folder_'.Str::lower(Str::random(12));
            }
        });
    }

    public function files(): HasMany
    {
        return $this->hasMany(FolderFile::class)->orderBy('relative_path');
    }
}
