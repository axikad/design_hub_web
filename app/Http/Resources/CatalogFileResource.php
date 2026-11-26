<?php

namespace App\Http\Resources;

use App\Support\CatalogFilePreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogFileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'relativePath' => $this->relative_path,
            'extension' => $this->extension,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'previewUri' => CatalogFilePreview::temporaryUrl($this->catalog, $this->resource),
        ];
    }
}
