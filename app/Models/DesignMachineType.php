<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DesignMachineType extends Model
{
    protected $fillable = ['public_id', 'name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            DesignOptionCategory::class,
            'design_category_machine_type',
        );
    }
}
