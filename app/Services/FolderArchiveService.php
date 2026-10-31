<?php

namespace App\Services;

use App\Models\Folder;
use FilesystemIterator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
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
        return match (Str::lower($upload->getClientOriginalExtension())) {
            'zip' => $this->extractZip($upload, $folder),
            'rar' => $this->extractRar($upload, $folder),
            default => throw new RuntimeException('Upload a valid ZIP or RAR archive.'),
        };
    }

    /**
     * @return array{zip_name: string, zip_path: string, file_count: int, total_size: int, files: list<array{name: string, relative_path: string, storage_path: string, extension: ?string, mime_type: string, size: int}>}
     */
    private function extractZip(UploadedFile $upload, Folder $folder): array
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

    /**
     * @return array{zip_name: string, zip_path: string, file_count: int, total_size: int, files: list<array{name: string, relative_path: string, storage_path: string, extension: ?string, mime_type: string, size: int}>}
     */
    private function extractRar(UploadedFile $upload, Folder $folder): array
    {
        if (class_exists(\RarArchive::class)) {
            return $this->extractRarWithExtension($upload, $folder);
        }

        return $this->extractRarWithCommand($upload, $folder);
    }

    /**
     * @return array{zip_name: string, zip_path: string, file_count: int, total_size: int, files: list<array{name: string, relative_path: string, storage_path: string, extension: ?string, mime_type: string, size: int}>}
     */
    private function extractRarWithExtension(UploadedFile $upload, Folder $folder): array
    {
        $archive = \RarArchive::open($upload->getRealPath());

        if ($archive === false) {
            throw new RuntimeException('The uploaded file is not a valid RAR archive.');
        }

        $version = Str::lower((string) Str::uuid());
        $basePath = "folders/{$folder->public_id}/{$version}";
        $archivePath = "{$basePath}/archive/source.rar";

        try {
            $entries = $archive->getEntries();

            if ($entries === false || $entries === []) {
                throw new RuntimeException('The RAR archive is empty.');
            }

            Storage::disk('local')->put($archivePath, fopen($upload->getRealPath(), 'rb'));
            $files = [];
            $seenPaths = [];
            $totalSize = 0;

            foreach ($entries as $entry) {
                if ($entry->isDirectory()) {
                    continue;
                }

                if ($entry->getHostOs() === \RarEntry::HOST_UNIX
                    && ($entry->getAttr() & \RarEntry::ATTRIBUTE_UNIX_FINAL_QUARTET) === \RarEntry::ATTRIBUTE_UNIX_SYM_LINK) {
                    throw new RuntimeException('The archive may not contain symbolic links.');
                }

                if ($entry->isEncrypted()) {
                    throw new RuntimeException('Password-protected RAR archives are not supported.');
                }

                $relativePath = $this->safeRelativePath($entry->getName());

                if (isset($seenPaths[$relativePath])) {
                    throw new RuntimeException("The archive contains the duplicate path: {$relativePath}");
                }

                $size = $entry->getUnpackedSize();
                $totalSize += $size;

                if (count($files) >= self::MAX_FILES) {
                    throw new RuntimeException('The RAR archive may contain at most '.self::MAX_FILES.' files.');
                }

                if ($totalSize > self::MAX_EXTRACTED_BYTES) {
                    throw new RuntimeException('The extracted RAR content may not exceed 512 MB.');
                }

                $stream = $entry->getStream();

                if ($stream === false) {
                    throw new RuntimeException("Could not extract: {$relativePath}");
                }

                $storagePath = "{$basePath}/files/{$relativePath}";

                try {
                    Storage::disk('local')->put($storagePath, $stream);
                } finally {
                    fclose($stream);
                }

                if (Storage::disk('local')->size($storagePath) !== $size) {
                    throw new RuntimeException("The extracted file is incomplete: {$relativePath}");
                }

                $absolutePath = Storage::disk('local')->path($storagePath);
                $name = basename($relativePath);
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                $files[] = [
                    'name' => $name,
                    'relative_path' => $relativePath,
                    'storage_path' => $storagePath,
                    'extension' => $extension !== '' ? Str::lower($extension) : null,
                    'mime_type' => mime_content_type($absolutePath) ?: 'application/octet-stream',
                    'size' => $size,
                ];
                $seenPaths[$relativePath] = true;
            }

            if ($files === []) {
                throw new RuntimeException('The RAR archive does not contain any files.');
            }

            return [
                'zip_name' => basename($upload->getClientOriginalName()),
                'zip_path' => $archivePath,
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

    /**
     * @return array{zip_name: string, zip_path: string, file_count: int, total_size: int, files: list<array{name: string, relative_path: string, storage_path: string, extension: ?string, mime_type: string, size: int}>}
     */
    private function extractRarWithCommand(UploadedFile $upload, Folder $folder): array
    {
        $extractor = $this->rarExtractor();

        if ($extractor === null) {
            throw new RuntimeException('RAR extraction requires the PHP rar extension or bsdtar (libarchive) on the server.');
        }

        $version = Str::lower((string) Str::uuid());
        $basePath = "folders/{$folder->public_id}/{$version}";
        $archivePath = "{$basePath}/archive/source.rar";
        $temporaryPath = "{$basePath}/extracting";

        try {
            Storage::disk('local')->put($archivePath, fopen($upload->getRealPath(), 'rb'));
            Storage::disk('local')->makeDirectory($temporaryPath);

            $absoluteArchivePath = Storage::disk('local')->path($archivePath);
            $absoluteTemporaryPath = Storage::disk('local')->path($temporaryPath);
            $listing = new Process([$extractor, '-tf', $absoluteArchivePath]);
            $listing->setTimeout(60)->run();

            if (! $listing->isSuccessful()) {
                throw new RuntimeException('The uploaded file is not a valid RAR archive.');
            }

            $entries = preg_split('/\R/', trim($listing->getOutput())) ?: [];
            $listedFiles = 0;

            foreach ($entries as $entryName) {
                $entryName = trim($entryName);

                if ($entryName === '' || str_ends_with(str_replace('\\', '/', $entryName), '/')) {
                    continue;
                }

                $this->safeRelativePath($entryName);
                $listedFiles++;

                if ($listedFiles > self::MAX_FILES) {
                    throw new RuntimeException('The RAR archive may contain at most '.self::MAX_FILES.' files.');
                }
            }

            if ($listedFiles < 1) {
                throw new RuntimeException('The RAR archive is empty.');
            }

            $extraction = new Process([$extractor, '-xf', $absoluteArchivePath, '-C', $absoluteTemporaryPath]);
            $extraction->setTimeout(120)->run();

            if (! $extraction->isSuccessful()) {
                throw new RuntimeException('The uploaded RAR archive could not be extracted.');
            }

            $files = $this->storeExtractedDirectory($absoluteTemporaryPath, $basePath);

            return [
                'zip_name' => basename($upload->getClientOriginalName()),
                'zip_path' => $archivePath,
                'file_count' => count($files),
                'total_size' => array_sum(array_column($files, 'size')),
                'files' => $files,
            ];
        } catch (\Throwable $exception) {
            Storage::disk('local')->deleteDirectory($basePath);
            throw $exception;
        } finally {
            Storage::disk('local')->deleteDirectory($temporaryPath);
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
            throw new RuntimeException('The archive contains an unsafe file path.');
        }

        $segments = explode('/', $normalized);

        if (in_array('..', $segments, true)) {
            throw new RuntimeException('The archive contains an unsafe file path.');
        }

        $segments = array_values(array_filter($segments, fn (string $segment) => $segment !== '' && $segment !== '.'));

        if ($segments === []) {
            throw new RuntimeException('The archive contains an invalid file path.');
        }

        return implode('/', $segments);
    }

    /** @return list<array{name: string, relative_path: string, storage_path: string, extension: ?string, mime_type: string, size: int}> */
    private function storeExtractedDirectory(string $absoluteDirectory, string $basePath): array
    {
        $root = realpath($absoluteDirectory);

        if ($root === false) {
            throw new RuntimeException('The extracted archive directory is unavailable.');
        }

        $files = [];
        $seenPaths = [];
        $totalSize = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('The archive may not contain symbolic links.');
            }

            if (! $file->isFile()) {
                continue;
            }

            $absolutePath = $file->getRealPath();
            $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

            if ($absolutePath === false || ! str_starts_with($absolutePath, $rootPrefix)) {
                throw new RuntimeException('The archive contains an unsafe file path.');
            }

            $relativePath = $this->safeRelativePath(str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($rootPrefix))));

            if (isset($seenPaths[$relativePath])) {
                throw new RuntimeException("The archive contains the duplicate path: {$relativePath}");
            }

            $size = $file->getSize();
            $totalSize += $size;

            if (count($files) >= self::MAX_FILES) {
                throw new RuntimeException('The archive may contain at most '.self::MAX_FILES.' files.');
            }

            if ($totalSize > self::MAX_EXTRACTED_BYTES) {
                throw new RuntimeException('The extracted archive content may not exceed 512 MB.');
            }

            $storagePath = "{$basePath}/files/{$relativePath}";
            $stream = fopen($absolutePath, 'rb');

            if ($stream === false) {
                throw new RuntimeException("Could not store: {$relativePath}");
            }

            try {
                Storage::disk('local')->put($storagePath, $stream);
            } finally {
                fclose($stream);
            }

            $name = basename($relativePath);
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $files[] = [
                'name' => $name,
                'relative_path' => $relativePath,
                'storage_path' => $storagePath,
                'extension' => $extension !== '' ? Str::lower($extension) : null,
                'mime_type' => mime_content_type($absolutePath) ?: 'application/octet-stream',
                'size' => $size,
            ];
            $seenPaths[$relativePath] = true;
        }

        if ($files === []) {
            throw new RuntimeException('The archive does not contain any files.');
        }

        return $files;
    }

    private function rarExtractor(): ?string
    {
        $configured = trim((string) config('designhub.folder_archive_extractor'));
        $candidates = array_values(array_unique(array_filter([$configured, 'bsdtar', 'tar'])));

        foreach ($candidates as $candidate) {
            try {
                $process = new Process([$candidate, '--version']);
                $process->setTimeout(5)->run();
                $output = Str::lower($process->getOutput().' '.$process->getErrorOutput());

                if ($process->isSuccessful() && (str_contains($output, 'bsdtar') || str_contains($output, 'libarchive'))) {
                    return $candidate;
                }
            } catch (\Throwable) {
                // Try the next candidate. The caller returns an actionable configuration error.
            }
        }

        return null;
    }
}
