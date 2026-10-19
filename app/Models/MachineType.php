<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class MachineType extends Model
{
    protected $fillable = ['machine_id', 'public_id', 'name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (MachineType $machineType) {
            if (! $machineType->public_id) {
                $machineType->public_id = 'machine_type_'.Str::lower(Str::random(10));
            }
        });
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(DesignCategory::class, 'category_machine_type');
    }
}
