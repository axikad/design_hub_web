<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FolderResource;
use App\Models\Folder;
use App\Models\FolderPurchase;
use App\Support\ApiResponse;
use App\Support\FolderFilePreview;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FolderController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'],
        ]);

        $folders = Folder::query()
            ->with('previewFiles')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated(
            $folders,
            $folders->getCollection()->map(
                fn (Folder $folder) => (new FolderResource($folder))->resolve($request)
            )->values(),
            'Folders retrieved successfully.',
        );
    }

    public function show(Request $request, string $folderId)
    {
        $folder = Folder::query()
            ->with(['files.folder', 'previewFiles'])
            ->where('public_id', $folderId)
            ->first();

        if ($folder === null) {
            return ApiResponse::error('Folder not found.', 'folder_not_found', 404);
        }

        return ApiResponse::data(
            (new FolderResource($folder))->resolve($request),
            message: 'Folder retrieved successfully.',
        );
    }

    public function streamPreview(string $folderId, string $fileId): BinaryFileResponse
    {
        $folder = Folder::query()->where('public_id', $folderId)->firstOrFail();
        $file = $folder->files()->where('public_id', $fileId)->firstOrFail();
        $mimeType = FolderFilePreview::mimeType($file);

        abort_unless($mimeType !== null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($file->storage_path), 404);

        $safeName = str_replace(['"', "\r", "\n"], '', $file->name);

        return response()->file($disk->path($file->storage_path), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
            'Content-Security-Policy' => "sandbox; default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }

    public function download(Request $request, string $folderId)
    {
        $folder = Folder::query()->where('public_id', $folderId)->first();
        if ($folder === null) {
            return ApiResponse::error('Folder not found.', 'folder_not_found', 404);
        }

        $purchased = FolderPurchase::query()
            ->where('buyer_id', $request->user()->id)
            ->where('folder_id', $folder->id)
            ->where('status', 'paid')
            ->exists();

        if (! $purchased) {
            return ApiResponse::error(
                'Purchase this folder to download its ZIP file.',
                'folder_download_forbidden',
                403,
            );
        }

        if (! Storage::disk('local')->exists($folder->zip_path)) {
            return ApiResponse::error('Folder ZIP file is unavailable.', 'folder_file_unavailable', 404);
        }

        $expiresAt = now()->addMinutes((int) config('designhub.download_url_ttl_minutes'));

        return ApiResponse::data([
            'folderId' => $folder->public_id,
            'downloadUri' => URL::temporarySignedRoute(
                'api.v1.files.folders.download',
                $expiresAt,
                ['folderId' => $folder->public_id],
            ),
            'expiresAt' => $expiresAt->utc()->toIso8601String(),
        ], message: 'Folder download link created successfully.');
    }

    public function streamDownload(string $folderId)
    {
        $folder = Folder::query()->where('public_id', $folderId)->firstOrFail();
        $disk = Storage::disk('local');
        abort_unless($disk->exists($folder->zip_path), 404);

        return $disk->download($folder->zip_path, $folder->zip_name, [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
