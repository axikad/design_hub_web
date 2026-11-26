<?php

namespace App\Http\Resources;

use App\Support\CatalogFilePreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $cover = $this->relationLoaded('previewFiles') ? $this->previewFiles->first() : null;

        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'pricePaise' => $this->price_paise,
            'currency' => 'INR',
            'fileCount' => $this->file_count,
            'totalSize' => $this->total_size,
            'coverImageUri' => $cover ? CatalogFilePreview::temporaryUrl($this->resource, $cover) : null,
            'zipName' => $this->when($this->relationLoaded('files'), $this->zip_name),
            'files' => CatalogFileResource::collection($this->whenLoaded('files')),
            'createdAt' => $this->created_at?->utc()->toIso8601String(),
            'updatedAt' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }
}
