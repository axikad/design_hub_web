<?php

namespace App\Services;

use App\Models\Folder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class FolderArchiveService
{
    private const MAX_FILES = 500;

    private const MAX_EXTRACTED_BYTES = 536870912;

    /**
     * @return array{zip_name: string, zip_path: string, file_count: int, total_size: int, files: list<array{name: string, relative_path: string, storage_path: string, extension: ?string, mime_type: string, size: int}>}
     */
    public function extract(UploadedFile $upload, Folder $folder): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP support is not enabled on this server.');
        }

        $version = Str::lower((string) Str::uuid());
        $basePath = "folders/{$folder->public_id}/{$version}";
        $zipPath = "{$basePath}/archive/source.zip";
        $archive = new ZipArchive;

        if ($archive->open($upload->getRealPath()) !== true) {
            throw new RuntimeException('The uploaded file is not a valid ZIP archive.');
        }

        try {
            if ($archive->numFiles < 1) {
                throw new RuntimeException('The ZIP archive is empty.');
            }

            if ($archive->numFiles > self::MAX_FILES) {
                throw new RuntimeException('The ZIP archive may contain at most '.self::MAX_FILES.' files.');
            }

            Storage::disk('local')->put($zipPath, fopen($upload->getRealPath(), 'rb'));

            $files = [];
            $seenPaths = [];
            $totalSize = 0;

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                $entryName = (string) ($entry['name'] ?? '');

                if ($entryName === '' || str_ends_with($entryName, '/')) {
                    continue;
                }

                $relativePath = $this->safeRelativePath($entryName);

                if (isset($seenPaths[$relativePath])) {
                    throw new RuntimeException("The ZIP contains the duplicate path: {$relativePath}");
                }

                $size = (int) ($entry['size'] ?? 0);
                $totalSize += $size;

                if ($totalSize > self::MAX_EXTRACTED_BYTES) {
                    throw new RuntimeException('The extracted ZIP content may not exceed 512 MB.');
                }

                $stream = $archive->getStream($entryName);

                if ($stream === false) {
                    throw new RuntimeException("Could not extract: {$relativePath}");
                }

                $storagePath = "{$basePath}/files/{$relativePath}";

                try {
                    Storage::disk('local')->put($storagePath, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                $absolutePath = Storage::disk('local')->path($storagePath);
                $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';
                $name = basename($relativePath);
                $extension = pathinfo($name, PATHINFO_EXTENSION);

                $files[] = [
                    'name' => $name,
                    'relative_path' => $relativePath,
                    'storage_path' => $storagePath,
                    'extension' => $extension !== '' ? Str::lower($extension) : null,
                    'mime_type' => $mimeType,
                    'size' => $size,
                ];
                $seenPaths[$relativePath] = true;
            }

            if ($files === []) {
                throw new RuntimeException('The ZIP archive does not contain any files.');
            }

            return [
                'zip_name' => basename($upload->getClientOriginalName()),
                'zip_path' => $zipPath,
                'file_count' => count($files),
                'total_size' => $totalSize,
                'files' => $files,
            ];
        } catch (\Throwable $exception) {
            Storage::disk('local')->deleteDirectory($basePath);
            throw $exception;
        } finally {
            $archive->close();
        }
    }

    public function deleteArchiveVersion(?string $zipPath): void
    {
        if (! $zipPath) {
            return;
        }

        Storage::disk('local')->deleteDirectory(dirname(dirname($zipPath)));
    }

    public function deleteFolder(Folder $folder): void
    {
        Storage::disk('local')->deleteDirectory("folders/{$folder->public_id}");
    }

    private function safeRelativePath(string $entryName): string
    {
        $normalized = str_replace('\\', '/', $entryName);

        if (str_contains($normalized, "\0") || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized)) {
            throw new RuntimeException('The ZIP archive contains an unsafe file path.');
        }

        $segments = explode('/', $normalized);

        if (in_array('..', $segments, true)) {
            throw new RuntimeException('The ZIP archive contains an unsafe file path.');
        }

        $segments = array_values(array_filter($segments, fn (string $segment) => $segment !== '' && $segment !== '.'));

        if ($segments === []) {
            throw new RuntimeException('The ZIP archive contains an invalid file path.');
        }

        return implode('/', $segments);
    }
}
