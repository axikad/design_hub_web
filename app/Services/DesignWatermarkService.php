<?php

namespace App\Services;

use App\Models\Design;
use App\Models\Machine;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DesignWatermarkService
{
    public function syncMachine(Machine $machine): void
    {
        $machine->designs()->listed()->eachById(fn (Design $design) => $this->syncDesign($design));
    }

    public function syncDesign(Design $design): void
    {
        $design->loadMissing(['machine', 'machineType.machine']);
        $machine = $design->machine ?? $design->machineType?->machine;
        $config = $machine?->normalizedWatermarkConfig() ?? Machine::defaultWatermarkConfig();
        $images = $this->images($design);

        foreach ($images as $image) {
            if (filled($image['watermarkedPath'] ?? null)) {
                Storage::disk('public')->delete($image['watermarkedPath']);
            }
        }

        if (! $config['enabled'] || $images === []) {
            $cleanImages = collect($images)->map(function (array $image) {
                unset($image['watermarkedPath']);

                return $image;
            })->values()->all();

            if ($design->images !== $cleanImages) {
                $design->updateQuietly(['images' => $cleanImages]);
            }

            return;
        }

        $watermarked = collect($images)->map(function (array $image, int $index) use ($design, $config) {
            $image['watermarkedPath'] = $this->generate(
                $image['path'],
                $design->public_id,
                $index,
                $config['text'],
                $config['opacity'],
                $config['textSize'],
                $config['spacing'],
            );

            return $image;
        })->values()->all();

        $design->updateQuietly(['images' => $watermarked]);
    }

    private function generate(
        string $sourcePath,
        string $designId,
        int $index,
        string $text,
        int $opacity,
        int $textSize,
        int $spacing,
    ): string {
        $disk = Storage::disk('public');

        if (! $disk->exists($sourcePath)) {
            throw new RuntimeException('The original design image is unavailable for watermarking.');
        }

        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('The GD image extension is required for watermarking.');
        }

        $contents = $disk->get($sourcePath);
        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('The design image format cannot be watermarked.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagealphablending($canvas, true);

        $this->tileText($canvas, $text, $opacity, $textSize, $spacing);

        $mime = $disk->mimeType($sourcePath) ?: 'image/jpeg';
        [$extension, $encoded] = $this->encode($canvas, $mime);
        imagedestroy($canvas);
        imagedestroy($source);

        $fingerprint = substr(hash('sha256', $contents.$text.$opacity.$textSize.$spacing), 0, 16);
        $path = "watermarked/{$designId}/{$index}-{$fingerprint}.{$extension}";
        $disk->put($path, $encoded);

        return $path;
    }

    private function tileText(\GdImage $image, string $text, int $opacity, int $textSize, int $spacing): void
    {
        $font = $this->fontPath();
        $baseFontSize = min(imagesx($image), imagesy($image)) / 18;
        $fontSize = max(10, min(96, (int) round($baseFontSize * ($textSize / 100))));
        $alpha = 127 - (int) round(($opacity / 100) * 127);
        $stampWidth = max(260, (int) round(strlen($text) * $fontSize * 0.78) + 80);
        $stampHeight = max(100, $fontSize * 3);
        $stamp = imagecreatetruecolor($stampWidth, $stampHeight);
        imagealphablending($stamp, false);
        imagesavealpha($stamp, true);
        $transparent = imagecolorallocatealpha($stamp, 255, 255, 255, 127);
        imagefill($stamp, 0, 0, $transparent);
        imagealphablending($stamp, true);
        $white = imagecolorallocatealpha($stamp, 255, 255, 255, $alpha);
        $outline = imagecolorallocatealpha($stamp, 8, 67, 82, max(0, $alpha - 18));

        if ($font !== null && function_exists('imagettftext')) {
            $textX = 30;
            $textY = (int) ($stampHeight / 2);

            foreach ([[-2, 0], [2, 0], [0, -2], [0, 2], [2, 2]] as [$offsetX, $offsetY]) {
                imagettftext($stamp, $fontSize, 0, $textX + $offsetX, $textY + $offsetY, $outline, $font, $text);
            }

            imagettftext($stamp, $fontSize, 0, $textX, $textY, $white, $font, $text);
        } else {
            $textX = 30;
            $textY = (int) ($stampHeight / 2) - 8;
            imagestring($stamp, 5, $textX + 2, $textY + 2, $text, $outline);
            imagestring($stamp, 5, $textX, $textY, $text, $white);
        }

        $rotated = imagerotate($stamp, 28, $transparent);
        imagesavealpha($rotated, true);
        $stepX = max(120, (int) round((imagesx($rotated) - 35) * ($spacing / 100)));
        $stepY = max(80, (int) round((imagesy($rotated) - 20) * ($spacing / 100)));

        for ($y = -$stepY; $y < imagesy($image) + $stepY; $y += $stepY) {
            $row = (int) floor($y / $stepY);
            $offset = $row % 2 === 0 ? 0 : (int) ($stepX / 2);

            for ($x = -$stepX + $offset; $x < imagesx($image) + $stepX; $x += $stepX) {
                imagecopy($image, $rotated, $x, $y, 0, 0, imagesx($rotated), imagesy($rotated));
            }
        }

        imagedestroy($rotated);
        imagedestroy($stamp);
    }

    /** @return array{string, string} */
    private function encode(\GdImage $image, string $mime): array
    {
        ob_start();

        if ($mime === 'image/png') {
            imagepng($image, null, 6);
            $extension = 'png';
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            imagewebp($image, null, 88);
            $extension = 'webp';
        } else {
            imagejpeg($image, null, 90);
            $extension = 'jpg';
        }

        $contents = ob_get_clean();

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('The watermarked image could not be generated.');
        }

        return [$extension, $contents];
    }

    private function fontPath(): ?string
    {
        return collect([
            resource_path('fonts/DejaVuSans-Bold.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
        ])->first(fn (string $path) => is_file($path));
    }

    /** @return list<array{name: string, path: string, watermarkedPath?: string}> */
    private function images(Design $design): array
    {
        if (is_array($design->images) && $design->images !== []) {
            return collect($design->images)
                ->filter(fn (array $image) => filled($image['path'] ?? null))
                ->values()
                ->all();
        }

        return $design->preview_path === null ? [] : [[
            'name' => basename($design->preview_path),
            'path' => $design->preview_path,
        ]];
    }
}
