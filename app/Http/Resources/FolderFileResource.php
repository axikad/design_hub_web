<?php

namespace App\Http\Resources;

use App\Support\FolderFilePreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FolderFileResource extends JsonResource
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
            'previewUri' => FolderFilePreview::temporaryUrl($this->folder, $this->resource),
        ];
    }
}
