<?php

namespace App\Services;

use RuntimeException;

class GeminiImagePreprocessor
{
    /** @return array{contents: string, mime: string} */
    public function prepare(string $contents, string $mime): array
    {
        $mime = strtolower($mime);

        if (! function_exists('imagecreatefromstring')) {
            return ['contents' => $contents, 'mime' => $mime];
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return ['contents' => $contents, 'mime' => $mime];
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);
            $maximum = max(256, (int) config('designhub.image_matching.preprocessing.max_dimension', 1600));
            $scale = min(1, $maximum / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($target === false) {
                throw new RuntimeException('Unable to allocate an image normalization buffer.');
            }

            try {
                $white = imagecolorallocate($target, 255, 255, 255);
                imagefill($target, 0, 0, $white);
                imagealphablending($target, true);
                imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

                ob_start();
                imagejpeg(
                    $target,
                    null,
                    min(95, max(50, (int) config('designhub.image_matching.preprocessing.jpeg_quality', 85))),
                );
                $normalized = ob_get_clean();

                if (! is_string($normalized) || $normalized === '') {
                    throw new RuntimeException('Unable to normalize the image for Gemini.');
                }

                return ['contents' => $normalized, 'mime' => 'image/jpeg'];
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
    }
}
