<?php

namespace App\Services;

use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DesignPresenter
{
    public function __construct(
        private readonly PurchaseAccessService $access,
        private readonly ImageThumbnailService $thumbnails,
    ) {}

    public function forUser(Design $design, ?User $viewer): array
    {
        $design->loadMissing(['owner', 'type.category', 'machineType.machine', 'fileType']);
        $canAccessFile = $viewer !== null && $this->access->canDownloadStitch($viewer, $design);
        $images = $this->images($design);
        $files = $this->files($design, $canAccessFile);
        $formCategory = $design->category_id === null
            ? null
            : DesignCategory::query()->where('public_id', $design->category_id)->first();

        $payload = [
            'id' => $design->public_id,
            'machineId' => $design->machineType?->machine?->public_id,
            'machineTypeId' => $design->machine_type_id,
            'title' => $design->title,
            'typeId' => $design->type?->public_id ?? $design->category_id,
            'typeName' => $design->type?->name ?? $formCategory?->name,
            'designFileTypeId' => $design->fileType?->public_id,
            'designFileTypeName' => $design->fileType?->name,
            'designFileExtension' => $design->fileType?->extension,
            'categoryId' => $design->category_id ?? $design->type?->category?->public_id,
            'categoryName' => $formCategory?->name ?? $design->type?->category?->name,
            'showPrice' => $design->show_price,
            'pricePaise' => $design->price_paise,
            'details' => $design->details ?? $this->legacyDetails($design),
            'images' => $images,
            'files' => $files,
            'size' => $design->size,
            'color' => $design->color,
            'imageUri' => $images[0]['uri'] ?? $this->previewUrl($design),
            'fileName' => $design->file_name,
            'displayName' => $design->display_name,
            'fileUri' => $files[0]['uri'] ?? null,
            'hasFile' => $design->stitch_path !== null,
            'createdAt' => $design->created_at?->utc()->toIso8601String(),
            'ownerKey' => $design->owner?->ownerKey(),
        ];

        return $payload;
    }

    public function previewUrl(Design $design): ?string
    {
        if ($design->preview_path === null) {
            return null;
        }

        return Storage::disk('public')->url($design->preview_path);
    }

    public function stitchReferenceUrl(Design $design, int $fileIndex = 0): ?string
    {
        if ($design->stitch_path === null) {
            return null;
        }

        $url = url('/api/v1/designs/'.$design->public_id.'/download');

        return $fileIndex === 0 ? $url : $url.'?file='.$fileIndex;
    }

    private function images(Design $design): array
    {
        if (is_array($design->images) && $design->images !== []) {
            return collect($design->images)->map(fn (array $image) => [
                'name' => $image['name'],
                'uri' => Storage::disk('public')->url($image['path']),
                'thumbnailUri' => $this->thumbnails->url($image['path']),
            ])->values()->all();
        }

        if ($design->preview_path === null) {
            return [];
        }

        return [[
            'name' => basename($design->preview_path),
            'uri' => $this->previewUrl($design),
            'thumbnailUri' => $this->thumbnails->url($design->preview_path),
        ]];
    }

    private function files(Design $design, bool $canAccessFile): array
    {
        if (is_array($design->files) && $design->files !== []) {
            return collect($design->files)->map(fn (array $file, int $index) => [
                'name' => $file['name'],
                'displayName' => $design->displayNameFor($file['name']),
                'uri' => $canAccessFile ? $this->stitchReferenceUrl($design, $index) : null,
            ])->values()->all();
        }

        if ($design->stitch_path === null) {
            return [];
        }

        return [[
            'name' => $design->file_name ?? 'design.dst',
            'displayName' => $design->displayNameFor($design->file_name ?? 'design.dst'),
            'uri' => $canAccessFile ? $this->stitchReferenceUrl($design) : null,
        ]];
    }

    private function legacyDetails(Design $design): array
    {
        return [[
            'names' => [$design->title],
            'stitch' => '',
            'colour' => $design->color,
            'area' => $design->size,
            'height' => '',
            'width' => '',
        ]];
    }

    public function signedStitchDownload(Design $design): ?array
    {
        if ($design->stitch_path === null) {
            return null;
        }

        $expiresAt = now()->addMinutes((int) config('designhub.download_url_ttl_minutes'));

        return [
            'url' => Storage::disk('local')->temporaryUrl(
                $design->stitch_path,
                $expiresAt,
                ['ResponseContentDisposition' => 'attachment; filename="'.($design->file_name ?? 'design.dst').'"']
            ),
            'fileName' => $design->file_name ?? 'design.dst',
            'expiresAt' => $expiresAt->utc()->toIso8601String(),
        ];
    }
}
