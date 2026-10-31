<?php

namespace App\Models;

use App\Support\MachineDesignFormConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Machine extends Model
{
    protected $fillable = ['public_id', 'name', 'image_path', 'design_form_config', 'watermark_config', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['design_form_config' => 'array', 'watermark_config' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer'];
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

    public function designs(): HasMany
    {
        return $this->hasMany(Design::class);
    }

    /** @return array<string, bool|array<string, bool>> */
    public function normalizedDesignFormConfig(): array
    {
        return MachineDesignFormConfig::normalize($this->design_form_config);
    }

    /** @return array{enabled: bool, text: string, opacity: int} */
    public static function defaultWatermarkConfig(): array
    {
        return ['enabled' => false, 'text' => 'DesignHub', 'opacity' => 24];
    }

    /** @return array{enabled: bool, text: string, opacity: int} */
    public function normalizedWatermarkConfig(): array
    {
        $config = is_array($this->watermark_config) ? $this->watermark_config : [];
        $text = trim((string) ($config['text'] ?? 'DesignHub'));

        return [
            'enabled' => (bool) ($config['enabled'] ?? false),
            'text' => mb_substr($text !== '' ? $text : 'DesignHub', 0, 60),
            'opacity' => max(5, min((int) ($config['opacity'] ?? 24), 60)),
        ];
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
