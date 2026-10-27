<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageFingerprintService
{
    /** @return array{sha256: string, perceptual_hash: string, contents: string, mime: string} */
    public function fromPublicPath(string $path): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            throw new RuntimeException("Design image was not found at {$path}.");
        }

        $contents = $disk->get($path);

        return $this->fromContents($contents, $disk->mimeType($path) ?: 'image/jpeg');
    }

    /** @return array{sha256: string, perceptual_hash: string, contents: string, mime: string} */
    public function fromUploadedFile(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('The uploaded image could not be read.');
        }

        return $this->fromContents($contents, $file->getMimeType() ?: 'image/jpeg');
    }

    /** @return array{sha256: string, perceptual_hash: string, contents: string, mime: string} */
    public function fromContents(string $contents, string $mime): array
    {
        return [
            'sha256' => hash('sha256', $contents),
            'perceptual_hash' => $this->differenceHash($contents),
            'contents' => $contents,
            'mime' => $mime,
        ];
    }

    public function similarity(string $firstHash, string $secondHash): float
    {
        if (strlen($firstHash) !== 16 || strlen($secondHash) !== 16) {
            return 0.0;
        }

        $distance = 0;

        for ($index = 0; $index < 16; $index++) {
            $xor = hexdec($firstHash[$index]) ^ hexdec($secondHash[$index]);
            $distance += substr_count(decbin($xor), '1');
        }

        return round((1 - ($distance / 64)) * 100, 2);
    }

    private function differenceHash(string $contents): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('The GD image extension is required for image matching.');
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('The uploaded file is not a supported image.');
        }

        $sample = imagecreatetruecolor(9, 8);
        imagecopyresampled($sample, $source, 0, 0, 0, 0, 9, 8, imagesx($source), imagesy($source));

        $bits = '';

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $bits .= $this->brightness(imagecolorat($sample, $x, $y))
                    > $this->brightness(imagecolorat($sample, $x + 1, $y)) ? '1' : '0';
            }
        }

        imagedestroy($sample);
        imagedestroy($source);

        return collect(str_split($bits, 4))
            ->map(fn (string $nibble) => dechex(bindec($nibble)))
            ->implode('');
    }

    private function brightness(int $color): float
    {
        $red = ($color >> 16) & 0xFF;
        $green = ($color >> 8) & 0xFF;
        $blue = $color & 0xFF;

        return ($red * 0.299) + ($green * 0.587) + ($blue * 0.114);
    }
}
