<?php

namespace Tests\Unit;

use App\Services\ImageFingerprintService;
use PHPUnit\Framework\TestCase;

class ImageFingerprintServiceTest extends TestCase
{
    public function test_resized_copy_keeps_a_high_perceptual_similarity(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not installed.');
        }

        $source = imagecreatetruecolor(80, 80);
        $white = imagecolorallocate($source, 255, 255, 255);
        $black = imagecolorallocate($source, 0, 0, 0);
        imagefill($source, 0, 0, $white);
        imagefilledellipse($source, 40, 40, 42, 58, $black);
        $original = $this->pngContents($source);

        $resizedImage = imagecreatetruecolor(160, 160);
        imagecopyresampled($resizedImage, $source, 0, 0, 0, 0, 160, 160, 80, 80);
        $resized = $this->pngContents($resizedImage);

        imagedestroy($source);
        imagedestroy($resizedImage);

        $service = new ImageFingerprintService;
        $first = $service->fromContents($original, 'image/png');
        $second = $service->fromContents($resized, 'image/png');

        $this->assertNotSame($first['sha256'], $second['sha256']);
        $this->assertGreaterThanOrEqual(95, $service->similarity(
            $first['perceptual_hash'],
            $second['perceptual_hash'],
        ));
    }

    private function pngContents(\GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
