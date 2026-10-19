<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageThumbnailService
{
    public function ensure(string $originalPath): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($originalPath)) {
            return null;
        }

        $thumbnailPath = $this->pathFor($originalPath);
        if ($disk->exists($thumbnailPath)) {
            return $thumbnailPath;
        }

        $source = @imagecreatefromstring($disk->get($originalPath));
        if ($source === false) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $maxWidth = (int) config('designhub.thumbnail_max_width', 480);
        $maxHeight = (int) config('designhub.thumbnail_max_height', 480);
        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $thumbnail = imagecreatetruecolor($width, $height);

        $extension = Str::lower(pathinfo($thumbnailPath, PATHINFO_EXTENSION));
        if (in_array($extension, ['png', 'webp'], true)) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
            imagefilledrectangle($thumbnail, 0, 0, $width, $height, $transparent);
        }

        imagecopyresampled(
            $thumbnail,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight,
        );

        ob_start();
        match ($extension) {
            'png' => imagepng($thumbnail, null, 6),
            'webp' => imagewebp($thumbnail, null, 82),
            default => imagejpeg($thumbnail, null, 82),
        };
        $contents = ob_get_clean();

        imagedestroy($source);
        imagedestroy($thumbnail);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $disk->put($thumbnailPath, $contents, ['visibility' => 'public']);

        return $thumbnailPath;
    }

    public function url(string $originalPath): string
    {
        $thumbnailPath = $this->ensure($originalPath);

        return Storage::disk('public')->url($thumbnailPath ?? $originalPath);
    }

    private function pathFor(string $originalPath): string
    {
        $directory = trim(pathinfo($originalPath, PATHINFO_DIRNAME), '.\\/');
        $filename = pathinfo($originalPath, PATHINFO_FILENAME);
        $originalExtension = Str::lower(pathinfo($originalPath, PATHINFO_EXTENSION));
        $extension = in_array($originalExtension, ['png', 'webp'], true) ? $originalExtension : 'jpg';
        $prefix = $directory === '' ? 'thumbnails' : 'thumbnails/'.$directory;

        return $prefix.'/'.$filename.'-thumb.'.$extension;
    }
}
