<?php

namespace Tests\Unit;

use App\Services\DesignFiles\DesignFileInspector;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DesignFileInspectorTest extends TestCase
{
    public function test_it_extracts_common_metadata_from_a_dst_header(): void
    {
        $header = implode("\r", [
            'LA:TEST BORDER',
            'ST:    68450',
            'CO:  6',
            '+X:  950',
            '-X:    0',
            '+Y:  420',
            '-Y:    0',
            'AX:+00000',
            'AY:+00000',
            'MX:+00000',
            'MY:+00000',
            'PD:******',
        ])."\r\x1a";
        $content = str_pad($header, 512, "\x20")."\x00\x00\xf3";
        $file = UploadedFile::fake()->createWithContent('border.dst', $content);

        $result = app(DesignFileInspector::class)->inspect($file);

        $this->assertSame('Tajima DST', $result['format']['name']);
        $this->assertSame('TEST BORDER', $result['metadata']['label']);
        $this->assertSame(68450, $result['metadata']['stitchCount']);
        $this->assertSame(95.0, $result['metadata']['widthMm']);
        $this->assertSame(42.0, $result['metadata']['heightMm']);
        $this->assertSame(7, $result['metadata']['threadColorCount']);
    }
}
