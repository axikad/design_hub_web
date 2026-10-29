<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Machine extends Model
{
    protected $fillable = ['public_id', 'name', 'image_path', 'is_active', 'sort_order'];

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

    public function imageUrl(): ?string
    {
        return $this->image_path === null
            ? null
            : Storage::disk('public')->url($this->image_path);
    }

    public function designFileTypes(): BelongsToMany
    {
        return $this->belongsToMany(DesignFileType::class, 'machine_design_file_type');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
