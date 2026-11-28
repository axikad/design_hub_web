<?php

namespace App\Services\DesignFiles;

use App\Services\DesignFiles\Contracts\DesignFileMetadataParser;
use RuntimeException;

class DstMetadataParser implements DesignFileMetadataParser
{
    public function extensions(): array
    {
        return ['dst'];
    }

    public function formatName(): string
    {
        return 'Tajima DST';
    }

    public function parse(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The uploaded file could not be opened.');
        }

        try {
            $header = fread($handle, 512);
        } finally {
            fclose($handle);
        }

        if ($header === false || strlen($header) < 512) {
            throw new RuntimeException('This DST file is incomplete: its 512-byte header is missing.');
        }

        $fields = $this->headerFields($header);

        if (! isset($fields['ST'], $fields['+X'], $fields['-X'], $fields['+Y'], $fields['-Y'])) {
            throw new RuntimeException('The file does not contain a valid Tajima DST header.');
        }

        $stitchCount = $this->integer($fields, 'ST');
        $colorChanges = max(0, $this->integer($fields, 'CO'));
        $positiveX = max(0, $this->integer($fields, '+X'));
        $negativeX = max(0, $this->integer($fields, '-X'));
        $positiveY = max(0, $this->integer($fields, '+Y'));
        $negativeY = max(0, $this->integer($fields, '-Y'));

        return [
            'label' => trim($fields['LA'] ?? '') ?: null,
            'stitchCount' => max(0, $stitchCount),
            'colorChangeCount' => $colorChanges,
            'threadColorCount' => $colorChanges + 1,
            'widthMm' => round(($positiveX + $negativeX) / 10, 1),
            'heightMm' => round(($positiveY + $negativeY) / 10, 1),
            'boundsMm' => [
                'left' => round($negativeX / 10, 1),
                'right' => round($positiveX / 10, 1),
                'top' => round($positiveY / 10, 1),
                'bottom' => round($negativeY / 10, 1),
            ],
            'finalPositionMm' => [
                'x' => round($this->integer($fields, 'AX') / 10, 1),
                'y' => round($this->integer($fields, 'AY') / 10, 1),
            ],
            'header' => $fields,
        ];
    }

    /** @return array<string, string> */
    private function headerFields(string $header): array
    {
        $fields = [];
        $text = str_replace("\x1a", '', $header);

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            if (preg_match('/^(LA|ST|CO|[+\-][XY]|A[XY]|M[XY]|PD):\s*(.*?)\s*$/', trim($line, "\0 \t"), $matches) === 1) {
                $fields[$matches[1]] = trim($matches[2]);
            }
        }

        return $fields;
    }

    /** @param array<string, string> $fields */
    private function integer(array $fields, string $key): int
    {
        $value = $fields[$key] ?? '0';

        return preg_match('/-?\d+/', $value, $matches) === 1 ? (int) $matches[0] : 0;
    }
}
