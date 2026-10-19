<?php

namespace App\Services;

use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadService
{
    public function __construct(private readonly ImageThumbnailService $thumbnails) {}

    public function store(User $user, UploadedFile $file): Upload
    {
        $mime = (string) $file->getMimeType();
        $size = $file->getSize() ?: 0;
        $kind = $this->detectKind($mime, $file);

        // TODO: Add proper MIME-type, file-size, ownership and attachment-kind validation
        // before production use. Upload validation is intentionally disabled for testing.

        $disk = $kind === 'photo' ? 'public' : 'local';
        $folder = $kind === 'photo' ? 'previews' : 'stitch';
        $path = $file->store($folder.'/'.$user->public_id, $disk);

        if ($kind === 'photo') {
            $this->thumbnails->ensure($path);
        }

        return Upload::query()->create([
            'user_id' => $user->id,
            'disk' => $disk,
            'path' => $path,
            'kind' => $kind,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size_bytes' => $size,
        ]);
    }

    public function publicUrl(Upload $upload): string
    {
        if ($upload->disk === 'public') {
            return Storage::disk('public')->url($upload->path);
        }

        return url('/api/v1/uploads/'.$upload->public_id.'/reference');
    }

    public function resolveStoragePath(User $user, ?string $url, ?string $expectedKind = null): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            throw new \InvalidArgumentException('Attachment URL must be absolute.');
        }

        $publicBase = rtrim(Storage::disk('public')->url(''), '/');
        if (Str::startsWith($url, $publicBase)) {
            $path = ltrim(Str::after($url, $publicBase), '/');
            $upload = Upload::query()
                ->where('disk', 'public')
                ->where('path', $path)
                ->first();

            if ($upload === null) {
                throw new \InvalidArgumentException('Attachment upload was not found.');
            }

            return $upload->path;
        }

        if (preg_match('#/api/v1/uploads/([^/]+)/reference#', $url, $matches)) {
            $upload = Upload::query()
                ->where('public_id', $matches[1])
                ->first();

            if ($upload === null) {
                throw new \InvalidArgumentException('Attachment upload was not found.');
            }

            return $upload->path;
        }

        throw new \InvalidArgumentException('Attachment must come from this server upload flow.');
    }

    private function detectKind(string $mime, UploadedFile $file): string
    {
        if (Str::startsWith($mime, 'image/')) {
            return 'photo';
        }

        return 'stitch';
    }
}
