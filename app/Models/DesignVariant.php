<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignVariant extends Model
{
    protected $fillable = [
        'design_id',
        'design_type_id',
        'name_or_number',
        'stitch_count',
        'needle',
        'area',
        'height',
        'width',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'stitch_count' => 'integer',
            'needle' => 'integer',
            'area' => 'decimal:2',
            'height' => 'decimal:2',
            'width' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DesignType::class, 'design_type_id');
    }
}
