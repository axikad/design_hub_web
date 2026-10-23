<?php

namespace App\Support;

use App\Models\Folder;
use App\Models\FolderFile;
use Illuminate\Support\Facades\URL;

final class FolderFilePreview
{
    public static function mimeType(FolderFile $file): ?string
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

    public static function temporaryUrl(Folder $folder, FolderFile $file): ?string
    {
        if (self::mimeType($file) === null || ! $file->public_id) {
            return null;
        }

        return URL::temporarySignedRoute(
            'api.v1.files.folders.preview',
            now()->addMinutes((int) config('designhub.download_url_ttl_minutes')),
            ['folderId' => $folder->public_id, 'fileId' => $file->public_id],
        );
    }
}
