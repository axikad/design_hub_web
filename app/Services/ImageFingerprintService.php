<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageFingerprintService
{
    /** @return array{sha256: string, perceptual_hash: string, color_histogram: list<float>, grayscale_layout: list<float>, contents: string, mime: string} */
    public function fromPublicPath(string $path): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            throw new RuntimeException("Design image was not found at {$path}.");
        }

        $contents = $disk->get($path);

        return $this->fromContents($contents, $disk->mimeType($path) ?: 'image/jpeg');
    }

    /** @return array{sha256: string, perceptual_hash: string, color_histogram: list<float>, grayscale_layout: list<float>, contents: string, mime: string} */
    public function fromUploadedFile(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('The uploaded image could not be read.');
        }

        return $this->fromContents($contents, $file->getMimeType() ?: 'image/jpeg');
    }

    /** @return array{sha256: string, perceptual_hash: string, color_histogram: list<float>, grayscale_layout: list<float>, contents: string, mime: string} */
    public function fromContents(string $contents, string $mime): array
    {
        $features = $this->visualFeatures($contents);

        return [
            'sha256' => hash('sha256', $contents),
            'perceptual_hash' => $features['perceptual_hash'],
            'color_histogram' => $features['color_histogram'],
            'grayscale_layout' => $features['grayscale_layout'],
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

    /**
     * @param  list<float>  $firstHistogram
     * @param  list<float>  $firstLayout
     * @param  list<float>  $secondHistogram
     * @param  list<float>  $secondLayout
     * @return array{score: float, color: float, layout: float, hash: float}
     */
    public function visualSimilarity(
        array $firstHistogram,
        array $firstLayout,
        string $firstHash,
        array $secondHistogram,
        array $secondLayout,
        string $secondHash,
    ): array {
        $color = $this->histogramSimilarity($firstHistogram, $secondHistogram);
        $layout = $this->layoutSimilarity($firstLayout, $secondLayout);
        $hash = $this->similarity($firstHash, $secondHash);

        return [
            'score' => round(($color * 0.45) + ($layout * 0.35) + ($hash * 0.20), 2),
            'color' => $color,
            'layout' => $layout,
            'hash' => $hash,
        ];
    }

    /** @return array{perceptual_hash: string, color_histogram: list<float>, grayscale_layout: list<float>} */
    private function visualFeatures(string $contents): array
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('The GD image extension is required for image matching.');
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('The uploaded file is not a supported image.');
        }

        $sample = imagecreatetruecolor(128, 128);
        $white = imagecolorallocate($sample, 255, 255, 255);
        imagefill($sample, 0, 0, $white);
        imagecopyresampled($sample, $source, 0, 0, 0, 0, 128, 128, imagesx($source), imagesy($source));

        $histogram = array_fill(0, 64, 0);
        $layout = array_fill(0, 64, 0.0);

        for ($y = 0; $y < 128; $y++) {
            for ($x = 0; $x < 128; $x++) {
                [$red, $green, $blue] = $this->rgb(imagecolorat($sample, $x, $y));
                $histogram[(intdiv($red, 64) * 16) + (intdiv($green, 64) * 4) + intdiv($blue, 64)]++;
                $layout[(intdiv($y, 16) * 8) + intdiv($x, 16)] += $this->brightnessFromRgb($red, $green, $blue);
            }
        }

        $histogram = array_map(fn (int $count) => round($count / 16384, 8), $histogram);
        $layout = array_map(fn (float $total) => round($total / 256, 4), $layout);

        $hashSample = imagecreatetruecolor(9, 8);
        imagecopyresampled($hashSample, $sample, 0, 0, 0, 0, 9, 8, 128, 128);

        $bits = '';

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $bits .= $this->brightness(imagecolorat($hashSample, $x, $y))
                    > $this->brightness(imagecolorat($hashSample, $x + 1, $y)) ? '1' : '0';
            }
        }

        imagedestroy($hashSample);
        imagedestroy($sample);
        imagedestroy($source);

        return [
            'perceptual_hash' => collect(str_split($bits, 4))
                ->map(fn (string $nibble) => dechex(bindec($nibble)))
                ->implode(''),
            'color_histogram' => $histogram,
            'grayscale_layout' => $layout,
        ];
    }

    private function brightness(int $color): float
    {
        return $this->brightnessFromRgb(...$this->rgb($color));
    }

    /** @return array{int, int, int} */
    private function rgb(int $color): array
    {
        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
    }

    private function brightnessFromRgb(int $red, int $green, int $blue): float
    {
        return ($red * 0.299) + ($green * 0.587) + ($blue * 0.114);
    }

    /** @param list<float> $first @param list<float> $second */
    private function histogramSimilarity(array $first, array $second): float
    {
        if (count($first) !== 64 || count($second) !== 64) {
            return 0.0;
        }

        $overlap = 0.0;

        foreach ($first as $index => $value) {
            $overlap += min((float) $value, (float) $second[$index]);
        }

        return round(max(0, min(1, $overlap)) * 100, 2);
    }

    /** @param list<float> $first @param list<float> $second */
    private function layoutSimilarity(array $first, array $second): float
    {
        if (count($first) !== 64 || count($second) !== 64) {
            return 0.0;
        }

        $difference = 0.0;

        foreach ($first as $index => $value) {
            $difference += abs((float) $value - (float) $second[$index]);
        }

        return round(max(0, 1 - ($difference / (64 * 255))) * 100, 2);
    }
}
