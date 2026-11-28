<?php

namespace App\Services\DesignFiles;

use App\Services\DesignFiles\Contracts\DesignFileMetadataParser;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class DesignFileInspector
{
    /** @var array<string, DesignFileMetadataParser> */
    private array $parsers = [];

    public function __construct(DstMetadataParser $dst, PesMetadataParser $pes)
    {
        foreach ([$dst, $pes] as $parser) {
            foreach ($parser->extensions() as $extension) {
                $this->parsers[strtolower($extension)] = $parser;
            }
        }
    }

    /** @return list<string> */
    public function supportedExtensions(): array
    {
        return array_keys($this->parsers);
    }

    /** @return array<string, mixed> */
    public function inspect(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $parser = $this->parsers[$extension] ?? null;

        if ($parser === null) {
            throw new RuntimeException(sprintf(
                '.%s metadata parsing is not available yet. Supported demo formats: %s.',
                $extension ?: 'unknown',
                implode(', ', array_map(fn (string $item) => '.'.strtoupper($item), $this->supportedExtensions())),
            ));
        }

        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('The temporary upload is no longer available.');
        }

        return [
            'file' => [
                'name' => $file->getClientOriginalName(),
                'extension' => $extension,
                'mimeType' => $file->getClientMimeType() ?: 'application/octet-stream',
                'sizeBytes' => $file->getSize(),
                'sha256' => hash_file('sha256', $path),
            ],
            'format' => [
                'id' => $extension,
                'name' => $parser->formatName(),
            ],
            'metadata' => $parser->parse($path),
        ];
    }
}
