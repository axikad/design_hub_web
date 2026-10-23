<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolderFile extends Model
{
    protected $fillable = [
        'name',
        'relative_path',
        'storage_path',
        'extension',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}
