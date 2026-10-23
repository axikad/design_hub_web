<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FolderFile extends Model
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
        static::creating(function (FolderFile $file) {
            if (! $file->public_id) {
                $file->public_id = 'folder_file_'.Str::lower(Str::random(12));
            }
        });
    }

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}
