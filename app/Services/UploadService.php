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

        $maxBytes = $kind === 'photo'
            ? (int) config('designhub.upload_max_photo_bytes')
            : (int) config('designhub.upload_max_stitch_bytes');

        if ($size > $maxBytes) {
            throw new \InvalidArgumentException('File is too large.');
        }

        $allowed = $kind === 'photo'
            ? config('designhub.upload_photo_mimes')
            : config('designhub.upload_stitch_mimes');

        if (! in_array($mime, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported file type.');
        }

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
                ->where('user_id', $user->id)
                ->first();

            if ($upload === null || ($expectedKind !== null && $upload->kind !== $expectedKind)) {
                throw new \InvalidArgumentException('Attachment is not owned by you or has the wrong type.');
            }

            return $upload->path;
        }

        if (preg_match('#/api/v1/uploads/([^/]+)/reference#', $url, $matches)) {
            $upload = Upload::query()
                ->where('public_id', $matches[1])
                ->where('user_id', $user->id)
                ->first();

            if ($upload === null || ($expectedKind !== null && $upload->kind !== $expectedKind)) {
                throw new \InvalidArgumentException('Attachment is not owned by you or has the wrong type.');
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
