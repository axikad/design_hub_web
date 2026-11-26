<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResource;
use App\Models\Catalog;
use App\Models\CatalogPurchase;
use App\Services\CatalogArchiveService;
use App\Services\MarketplacePaymentSettings;
use App\Support\ApiResponse;
use App\Support\CatalogFilePreview;
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

class CatalogPackageController extends Controller
{
    public function __construct(
        private readonly CatalogArchiveService $archives,
        private readonly MarketplacePaymentSettings $paymentSettings,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'],
        ]);

        $catalogs = Catalog::query()
            ->with('previewFiles')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated(
            $catalogs,
            $catalogs->getCollection()->map(
                fn (Catalog $catalog) => (new CatalogResource($catalog))->resolve($request)
            )->values(),
            'Catalogs retrieved successfully.',
        );
    }

    public function show(Request $request, string $catalogId)
    {
        $catalog = Catalog::query()
            ->where('public_id', $catalogId)
            ->first();

        if ($catalog === null) {
            return ApiResponse::error('Catalog not found.', 'catalog_not_found', 404);
        }

        $cover = $catalog->previewFiles()->first();
        $catalog->setRelation('previewFiles', collect([$cover])->filter()->values());

        $files = $catalog->files()
            ->with('catalog')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();
        $catalog->setRelation('files', $files->getCollection());

        return ApiResponse::paginated(
            $files,
            (new CatalogResource($catalog))->resolve($request),
            'Catalog retrieved successfully.',
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
            'name' => ['required', 'string', 'max:150', Rule::unique('catalogs', 'name')],
            'pricePaise' => ['nullable', 'integer', 'min:0', 'max:9999999999', 'required_without_all:price,priceRupees'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2', 'required_without_all:pricePaise,priceRupees'],
            'priceRupees' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2', 'required_without_all:pricePaise,price'],
            'file' => ['required', 'file', 'extensions:zip,rar'],
        ])->validate();

        $pricePaise = array_key_exists('pricePaise', $validated) && $validated['pricePaise'] !== null
            ? (int) $validated['pricePaise']
            : (int) round(((float) ($validated['priceRupees'] ?? $validated['price'])) * 100);

        $catalog = Catalog::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'price_paise' => $pricePaise,
            'zip_name' => '',
            'zip_path' => '',
        ]);

        try {
            $extracted = $this->archives->extract($archive, $catalog);

            DB::transaction(function () use ($catalog, $extracted): void {
                $catalog->update(collect($extracted)->except('files')->all());
                $catalog->files()->createMany($extracted['files']);
            });
        } catch (RuntimeException $exception) {
            $this->deleteFailedCatalog($catalog);

            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            $this->deleteFailedCatalog($catalog);
            report($exception);

            throw ValidationException::withMessages(['file' => 'The archive could not be processed.']);
        }

        $catalog->load(['files.catalog', 'previewFiles']);

        return ApiResponse::data(
            (new CatalogResource($catalog))->resolve($request),
            201,
            'Catalog created successfully.',
        );
    }

    public function streamPreview(string $catalogId, string $fileId): BinaryFileResponse
    {
        $catalog = Catalog::query()->where('public_id', $catalogId)->firstOrFail();
        $file = $catalog->files()->where('public_id', $fileId)->firstOrFail();
        $mimeType = CatalogFilePreview::mimeType($file);

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

    public function download(Request $request, string $catalogId)
    {
        $catalog = Catalog::query()->where('public_id', $catalogId)->first();
        if ($catalog === null) {
            return ApiResponse::error('Catalog not found.', 'catalog_not_found', 404);
        }

        $purchased = CatalogPurchase::query()
            ->where('buyer_id', $request->user()->id)
            ->where('catalog_id', $catalog->id)
            ->where('status', 'paid')
            ->where('payment_environment', $this->paymentSettings->environment())
            ->exists();

        if (! $purchased) {
            return ApiResponse::error(
                'Purchase this catalog to download its archive file.',
                'catalog_download_forbidden',
                403,
            );
        }

        if (! Storage::disk('local')->exists($catalog->zip_path)) {
            return ApiResponse::error('Catalog archive file is unavailable.', 'catalog_file_unavailable', 404);
        }

        $expiresAt = now()->addMinutes((int) config('designhub.download_url_ttl_minutes'));

        return ApiResponse::data([
            'catalogId' => $catalog->public_id,
            'downloadUri' => URL::temporarySignedRoute(
                'api.v1.files.catalogs.download',
                $expiresAt,
                ['catalogId' => $catalog->public_id],
            ),
            'expiresAt' => $expiresAt->utc()->toIso8601String(),
        ], message: 'Catalog download link created successfully.');
    }

    public function streamDownload(string $catalogId)
    {
        $catalog = Catalog::query()->where('public_id', $catalogId)->firstOrFail();
        $disk = Storage::disk('local');
        abort_unless($disk->exists($catalog->zip_path), 404);

        return $disk->download($catalog->zip_path, $catalog->zip_name, [
            'Content-Type' => Str::endsWith(Str::lower($catalog->zip_name), '.rar') ? 'application/vnd.rar' : 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function deleteFailedCatalog(Catalog $catalog): void
    {
        $this->archives->deleteCatalog($catalog);
        $catalog->delete();
    }
}
