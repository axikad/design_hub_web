<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Machine extends Model
{
    protected $fillable = ['public_id', 'name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (Machine $machine) {
            if (! $machine->public_id) {
                $machine->public_id = 'machine_'.Str::lower(Str::random(10));
            }
        });
    }

    public function machineTypes(): HasMany
    {
        return $this->hasMany(MachineType::class);
    }
}
