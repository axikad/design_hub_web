<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Upload extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'disk',
        'path',
        'kind',
        'original_name',
        'mime',
        'size_bytes',
        'attached',
    ];

    protected function casts(): array
    {
        return [
            'attached' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Upload $upload) {
            if (! $upload->public_id) {
                $upload->public_id = 'upload_'.Str::lower(Str::random(12));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
