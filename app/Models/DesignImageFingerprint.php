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
        'vector_id',
        'vector_status',
        'vector_error',
    ];

    protected function casts(): array
    {
        return ['image_index' => 'integer'];
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
