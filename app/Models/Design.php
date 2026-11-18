<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Design extends Model
{
    protected $appends = ['display_name'];

    protected $fillable = [
        'public_id',
        'user_id',
        'design_type_id',
        'design_file_type_id',
        'machine_id',
        'machine_type_id',
        'category_id',
        'show_price',
        'publish_date',
        'details',
        'images',
        'files',
        'title',
        'price_paise',
        'size',
        'color',
        'sequence_type',
        'preview_path',
        'stitch_path',
        'file_name',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'price_paise' => 'integer',
            'show_price' => 'boolean',
            'sequence_type' => 'decimal:2',
            'publish_date' => 'date:Y-m-d',
            'details' => 'array',
            'images' => 'array',
            'files' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Design $design) {
            if (! $design->public_id) {
                $design->public_id = 'design_'.Str::lower(Str::random(12));
            }

            if ($design->publish_date === null) {
                $design->publish_date = now(config('designhub.marketplace_timezone'))->toDateString();
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DesignType::class, 'design_type_id');
    }

    public function fileType(): BelongsTo
    {
        return $this->belongsTo(DesignFileType::class, 'design_file_type_id');
    }

    public function machineType(): BelongsTo
    {
        return $this->belongsTo(MachineType::class, 'machine_type_id', 'public_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(DesignVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function imageFingerprints(): HasMany
    {
        return $this->hasMany(DesignImageFingerprint::class)->orderBy('image_index');
    }

    public function scopeListed($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopePublished($query, ?string $date = null)
    {
        $date ??= now(config('designhub.marketplace_timezone'))->toDateString();

        return $query->whereDate('publish_date', '<=', $date);
    }

    public function scopeScheduledFor($query, string $date)
    {
        return $query->whereDate('publish_date', $date);
    }

    public function scopeForUserMachines($query, ?User $user)
    {
        if ($user === null) {
            return $query;
        }

        $user->loadMissing('machines:id,public_id');
        $machineIds = $user->machines->pluck('public_id');

        if ($machineIds->isEmpty()) {
            return $query;
        }

        return $query->where(function ($machineFilter) use ($machineIds) {
            $machineFilter
                ->whereHas('machine', fn ($machineQuery) => $machineQuery->whereIn('machines.public_id', $machineIds))
                ->orWhere(function ($legacyQuery) use ($machineIds) {
                    $legacyQuery
                        ->whereNull('machine_id')
                        ->whereHas(
                            'machineType.machine',
                            fn ($machineQuery) => $machineQuery->whereIn('machines.public_id', $machineIds),
                        );
                });
        });
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->displayNameFor($this->file_name));
    }

    public function displayNameFor(?string $originalFileName): string
    {
        $selectedExtension = strtolower(ltrim((string) $this->fileType?->extension, '.'));
        $actualExtension = strtolower(pathinfo((string) $originalFileName, PATHINFO_EXTENSION));
        $title = trim($this->title);

        if ($selectedExtension !== '' && $actualExtension !== '') {
            return $title.'.'.$selectedExtension.' (.'.$actualExtension.')';
        }

        $extension = $selectedExtension !== '' ? $selectedExtension : $actualExtension;

        return $extension !== '' ? $title.'.'.$extension : $title;
    }
}
