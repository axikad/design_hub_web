<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignImageFingerprint extends Model
{
    protected $fillable = [
        'design_id',
        'image_index',
        'image_path',
        'sha256',
        'perceptual_hash',
        'color_histogram',
        'grayscale_layout',
        'embedding',
        'embedding_model',
        'embedding_status',
        'embedding_error',
    ];

    protected function casts(): array
    {
        return [
            'image_index' => 'integer',
            'color_histogram' => 'array',
            'grayscale_layout' => 'array',
            'embedding' => 'array',
        ];
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
