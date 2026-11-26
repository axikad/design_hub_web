<?php

namespace App\Support;

use App\Models\Catalog;
use App\Models\CatalogFile;
use Illuminate\Support\Facades\URL;

final class CatalogFilePreview
{
    public static function mimeType(CatalogFile $file): ?string
    {
        return match (strtolower((string) $file->extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            default => null,
        };
    }

    public static function temporaryUrl(Catalog $catalog, CatalogFile $file): ?string
    {
        if (self::mimeType($file) === null || ! $file->public_id) {
            return null;
        }

        return URL::temporarySignedRoute(
            'api.v1.files.catalogs.preview',
            now()->addMinutes((int) config('designhub.download_url_ttl_minutes')),
            ['catalogId' => $catalog->public_id, 'fileId' => $file->public_id],
        );
    }
}
