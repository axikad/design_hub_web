<?php

namespace App\Services\DesignFiles;

use App\Services\DesignFiles\Contracts\DesignFileMetadataParser;
use RuntimeException;

class PesMetadataParser implements DesignFileMetadataParser
{
    private const PEC_HEADER_SIZE = 512;

    public function extensions(): array
    {
        return ['pes'];
    }

    public function formatName(): string
    {
        return 'Brother PES';
    }

    public function parse(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('The uploaded file could not be opened.');
        }

        if (strlen($contents) < 12) {
            throw new RuntimeException('This PES file is incomplete.');
        }

        $signature = substr($contents, 0, 8);
        $version = $this->version($signature);
        $pesMetadata = [];

        if ($signature === '#PEC0001') {
            $pecOffset = 8;
        } elseif ($version !== null) {
            $pecOffset = $this->unsignedInt32LittleEndian($contents, 8);
            $pesMetadata = $this->pesMetadata($contents, $version, $pecOffset);
        } else {
            throw new RuntimeException('The file does not contain a recognized Brother PES signature.');
        }

        $pec = $this->parsePecBlock($contents, $pecOffset);

        return [
            'label' => $pec['label'],
            'stitchCount' => $pec['stitchCount'],
            'colorChangeCount' => $pec['colorChangeCount'],
            'threadColorCount' => $pec['threadColorCount'],
            'widthMm' => $pec['widthMm'],
            'heightMm' => $pec['heightMm'],
            'boundsMm' => $pec['boundsMm'],
            'finalPositionMm' => $pec['finalPositionMm'],
            'version' => $version,
            'pesMetadata' => $pesMetadata,
            'pec' => [
                'offset' => $pecOffset,
                'graphicByteStride' => $pec['graphicByteStride'],
                'graphicIconHeight' => $pec['graphicIconHeight'],
                'declaredWidthMm' => $pec['declaredWidthMm'],
                'declaredHeightMm' => $pec['declaredHeightMm'],
            ],
        ];
    }

    private function version(string $signature): float|int|null
    {
        if (preg_match('/^#PES(\d{4})$/', $signature, $matches) !== 1) {
            return null;
        }

        $raw = (int) $matches[1];

        if ($raw === 1) {
            return 1;
        }

        return $raw % 10 === 0 ? intdiv($raw, 10) : $raw / 10;
    }

    /** @return array<string, string> */
    private function pesMetadata(string $contents, float|int $version, int $pecOffset): array
    {
        if ($version < 4 || $pecOffset <= 16) {
            return [];
        }

        $cursor = 16;
        $labels = ['name', 'category', 'author', 'keywords', 'comments'];
        $metadata = [];

        foreach ($labels as $label) {
            if ($cursor >= $pecOffset || $cursor >= strlen($contents)) {
                break;
            }

            $length = ord($contents[$cursor]);
            $cursor++;

            if ($length === 0) {
                continue;
            }

            if ($cursor + $length > $pecOffset || $cursor + $length > strlen($contents)) {
                break;
            }

            $value = trim(substr($contents, $cursor, $length), "\0 \t\r\n");
            $cursor += $length;

            if ($value !== '') {
                $metadata[$label] = mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }
        }

        return $metadata;
    }

    /** @return array<string, mixed> */
    private function parsePecBlock(string $contents, int $offset): array
    {
        $length = strlen($contents);

        if ($offset < 0 || $offset + self::PEC_HEADER_SIZE + 16 > $length) {
            throw new RuntimeException('The PES file points to an incomplete PEC stitch block.');
        }

        if (substr($contents, $offset, 3) !== 'LA:') {
            throw new RuntimeException('The embedded PEC stitch block is invalid.');
        }

        $label = trim(substr($contents, $offset + 3, 16), "\0 \t\r\n");
        $graphicByteStride = ord($contents[$offset + 34]);
        $graphicIconHeight = ord($contents[$offset + 35]);
        $colorChanges = ord($contents[$offset + 48]);
        $threadColorCount = $colorChanges === 0xFF ? 0 : $colorChanges + 1;
        $stitchBlockOffset = $offset + self::PEC_HEADER_SIZE;
        $stitchBlockLength = $this->unsignedInt24LittleEndian($contents, $stitchBlockOffset + 2);

        if ($stitchBlockLength < 16 || $stitchBlockOffset + $stitchBlockLength > $length) {
            throw new RuntimeException('The embedded PEC stitch data is incomplete.');
        }

        if (substr($contents, $stitchBlockOffset + 5, 3) !== "\x31\xFF\xF0") {
            throw new RuntimeException('The embedded PEC stitch data has an invalid header.');
        }

        $declaredWidth = $this->unsignedInt16LittleEndian($contents, $stitchBlockOffset + 8);
        $declaredHeight = $this->unsignedInt16LittleEndian($contents, $stitchBlockOffset + 10);
        $commandsOffset = $stitchBlockOffset + 16;
        $commandsEnd = $stitchBlockOffset + $stitchBlockLength;
        $stitches = $this->decodeStitches($contents, $commandsOffset, $commandsEnd);

        return [
            'label' => $label !== '' ? $label : null,
            'stitchCount' => $stitches['stitchCount'],
            'colorChangeCount' => max($colorChanges === 0xFF ? 0 : $colorChanges, $stitches['colorChangeCount']),
            'threadColorCount' => $threadColorCount,
            'widthMm' => round($declaredWidth / 10, 1),
            'heightMm' => round($declaredHeight / 10, 1),
            'boundsMm' => [
                'left' => round($stitches['minX'] / 10, 1),
                'right' => round($stitches['maxX'] / 10, 1),
                'top' => round($stitches['maxY'] / 10, 1),
                'bottom' => round($stitches['minY'] / 10, 1),
            ],
            'finalPositionMm' => [
                'x' => round($stitches['x'] / 10, 1),
                'y' => round($stitches['y'] / 10, 1),
            ],
            'graphicByteStride' => $graphicByteStride,
            'graphicIconHeight' => $graphicIconHeight,
            'declaredWidthMm' => round($declaredWidth / 10, 1),
            'declaredHeightMm' => round($declaredHeight / 10, 1),
        ];
    }

    /** @return array{stitchCount: int, colorChangeCount: int, minX: int, maxX: int, minY: int, maxY: int, x: int, y: int} */
    private function decodeStitches(string $contents, int $cursor, int $end): array
    {
        $x = 0;
        $y = 0;
        $minX = 0;
        $maxX = 0;
        $minY = 0;
        $maxY = 0;
        $stitchCount = 0;
        $colorChangeCount = 0;
        $ended = false;

        while ($cursor < $end) {
            $first = ord($contents[$cursor++]);

            if ($first === 0xFF) {
                if ($cursor < $end && ord($contents[$cursor]) === 0x00) {
                    $cursor++;
                }
                $ended = true;
                break;
            }

            if ($cursor >= $end) {
                break;
            }

            $second = ord($contents[$cursor++]);

            if ($first === 0xFE && $second === 0xB0) {
                if ($cursor < $end) {
                    $cursor++;
                }
                $colorChangeCount++;

                continue;
            }

            $jump = false;
            $trim = false;

            if (($first & 0x80) !== 0) {
                $jump = ($first & 0x10) !== 0;
                $trim = ($first & 0x20) !== 0;
                $dx = $this->signed12(($first << 8) | $second);

                if ($cursor >= $end) {
                    break;
                }

                $second = ord($contents[$cursor++]);
            } else {
                $dx = $this->signed7($first);
            }

            if (($second & 0x80) !== 0) {
                $jump = $jump || ($second & 0x10) !== 0;
                $trim = $trim || ($second & 0x20) !== 0;

                if ($cursor >= $end) {
                    break;
                }

                $dy = $this->signed12(($second << 8) | ord($contents[$cursor++]));
            } else {
                $dy = $this->signed7($second);
            }

            $x += $dx;
            $y += $dy;
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);

            if (! $jump && ! $trim) {
                $stitchCount++;
            }
        }

        if (! $ended) {
            throw new RuntimeException('The embedded PEC stitch stream has no valid end marker.');
        }

        return compact('stitchCount', 'colorChangeCount', 'minX', 'maxX', 'minY', 'maxY', 'x', 'y');
    }

    private function signed7(int $value): int
    {
        return $value > 63 ? $value - 128 : $value;
    }

    private function signed12(int $value): int
    {
        $value &= 0xFFF;

        return $value > 0x7FF ? $value - 0x1000 : $value;
    }

    private function unsignedInt16LittleEndian(string $contents, int $offset): int
    {
        return unpack('vvalue', substr($contents, $offset, 2))['value'];
    }

    private function unsignedInt24LittleEndian(string $contents, int $offset): int
    {
        return ord($contents[$offset]) | (ord($contents[$offset + 1]) << 8) | (ord($contents[$offset + 2]) << 16);
    }

    private function unsignedInt32LittleEndian(string $contents, int $offset): int
    {
        return unpack('Vvalue', substr($contents, $offset, 4))['value'];
    }
}
