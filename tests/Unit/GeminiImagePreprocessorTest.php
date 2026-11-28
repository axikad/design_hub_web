<?php

namespace Tests\Unit;

use App\Services\GeminiImagePreprocessor;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('gd')]
class GeminiImagePreprocessorTest extends TestCase
{
    public function test_it_resizes_and_reencodes_an_image_without_touching_the_original_file(): void
    {
        config([
            'designhub.image_matching.preprocessing.max_dimension' => 800,
            'designhub.image_matching.preprocessing.jpeg_quality' => 80,
        ]);

        $image = imagecreatetruecolor(1600, 1200);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 220));
        ob_start();
        imagepng($image);
        $original = ob_get_clean();
        imagedestroy($image);

        $prepared = app(GeminiImagePreprocessor::class)->prepare($original, 'image/png');
        $dimensions = getimagesizefromstring($prepared['contents']);

        $this->assertSame('image/jpeg', $prepared['mime']);
        $this->assertSame(800, $dimensions[0]);
        $this->assertSame(600, $dimensions[1]);
        $this->assertNotSame($original, $prepared['contents']);
    }
}
