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

    public function test_it_extracts_common_metadata_from_a_pes_pec_block(): void
    {
        $file = UploadedFile::fake()->createWithContent('border.pes', $this->pesContent());

        $result = app(DesignFileInspector::class)->inspect($file);

        $this->assertSame('Brother PES', $result['format']['name']);
        $this->assertSame(1, $result['metadata']['version']);
        $this->assertSame('TEST PES', $result['metadata']['label']);
        $this->assertSame(2, $result['metadata']['stitchCount']);
        $this->assertSame(1, $result['metadata']['colorChangeCount']);
        $this->assertSame(2, $result['metadata']['threadColorCount']);
        $this->assertSame(3.0, $result['metadata']['widthMm']);
        $this->assertSame(2.0, $result['metadata']['heightMm']);
        $this->assertSame(3.0, $result['metadata']['finalPositionMm']['x']);
        $this->assertSame(2.0, $result['metadata']['finalPositionMm']['y']);
    }

    public function test_it_rejects_a_pes_file_with_an_invalid_pec_offset(): void
    {
        $file = UploadedFile::fake()->createWithContent('broken.pes', '#PES0001'.pack('V', 9999));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('incomplete PEC stitch block');

        app(DesignFileInspector::class)->inspect($file);
    }

    private function pesContent(): string
    {
        $label = str_pad('TEST PES', 16);
        $pecHeader = 'LA:'.$label."\r".str_repeat(' ', 12)."\xFF\x00".chr(6).chr(38);
        $pecHeader .= str_repeat(' ', 12).chr(1).chr(5).chr(8);
        $pecHeader = str_pad($pecHeader, 512, ' ');

        $commands = chr(10).chr(20)."\xFE\xB0\x02".chr(20).chr(0)."\xFF\x00";
        $blockLength = 16 + strlen($commands);
        $length24 = chr($blockLength & 0xFF).chr(($blockLength >> 8) & 0xFF).chr(($blockLength >> 16) & 0xFF);
        $stitchBlock = "\x00\x00".$length24."\x31\xFF\xF0".pack('v4', 30, 20, 0x1E0, 0x1B0).$commands;
        $pecOffset = 12;

        return '#PES0001'.pack('V', $pecOffset).$pecHeader.$stitchBlock;
    }
}
