<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FolderResource;
use App\Models\Folder;
use App\Models\FolderPurchase;
use App\Services\FolderArchiveService;
use App\Services\MarketplacePaymentSettings;
use App\Support\ApiResponse;
use App\Support\FolderFilePreview;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FolderController extends Controller
{
    public function __construct(
        private readonly FolderArchiveService $archives,
        private readonly MarketplacePaymentSettings $paymentSettings,
    ) {}

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
            ->where('public_id', $folderId)
            ->first();

        if ($folder === null) {
            return ApiResponse::error('Folder not found.', 'folder_not_found', 404);
        }

        $cover = $folder->previewFiles()->first();
        $folder->setRelation('previewFiles', collect([$cover])->filter()->values());

        $files = $folder->files()
            ->with('folder')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();
        $folder->setRelation('files', $files->getCollection());

        return ApiResponse::paginated(
            $files,
            (new FolderResource($folder))->resolve($request),
            'Folder retrieved successfully.',
        );
    }

    public function store(Request $request)
    {
        $archive = $request->file('file')
            ?? $request->file('zipFile')
            ?? $request->file('archive');

        $payload = [
            ...$request->all(),
            'name' => trim((string) $request->input('name')),
            'file' => $archive,
        ];

        $validated = validator($payload, [
            'name' => ['required', 'string', 'max:150', Rule::unique('folders', 'name')],
            'pricePaise' => ['nullable', 'integer', 'min:0', 'max:9999999999', 'required_without_all:price,priceRupees'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2', 'required_without_all:pricePaise,priceRupees'],
            'priceRupees' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2', 'required_without_all:pricePaise,price'],
            'file' => ['required', 'file', 'extensions:zip,rar'],
        ])->validate();

        $pricePaise = array_key_exists('pricePaise', $validated) && $validated['pricePaise'] !== null
            ? (int) $validated['pricePaise']
            : (int) round(((float) ($validated['priceRupees'] ?? $validated['price'])) * 100);

        $folder = Folder::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'price_paise' => $pricePaise,
            'zip_name' => '',
            'zip_path' => '',
        ]);

        try {
            $extracted = $this->archives->extract($archive, $folder);

            DB::transaction(function () use ($folder, $extracted): void {
                $folder->update(collect($extracted)->except('files')->all());
                $folder->files()->createMany($extracted['files']);
            });
        } catch (RuntimeException $exception) {
            $this->deleteFailedFolder($folder);

            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            $this->deleteFailedFolder($folder);
            report($exception);

            throw ValidationException::withMessages(['file' => 'The archive could not be processed.']);
        }

        $folder->load(['files.folder', 'previewFiles']);

        return ApiResponse::data(
            (new FolderResource($folder))->resolve($request),
            201,
            'Folder created successfully.',
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
            ->where('payment_environment', $this->paymentSettings->environment())
            ->exists();

        if (! $purchased) {
            return ApiResponse::error(
                'Purchase this folder to download its archive file.',
                'folder_download_forbidden',
                403,
            );
        }

        if (! Storage::disk('local')->exists($folder->zip_path)) {
            return ApiResponse::error('Folder archive file is unavailable.', 'folder_file_unavailable', 404);
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
            'Content-Type' => Str::endsWith(Str::lower($folder->zip_name), '.rar') ? 'application/vnd.rar' : 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function deleteFailedFolder(Folder $folder): void
    {
        $this->archives->deleteFolder($folder);
        $folder->delete();
    }
}
