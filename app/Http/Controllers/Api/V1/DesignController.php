<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DuplicateDesignImageException;
use App\Exceptions\ImageMatchingUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\DesignFileType;
use App\Models\DesignType;
use App\Models\Machine;
use App\Models\MachineType;
use App\Services\DesignImageMatchingService;
use App\Services\DesignWatermarkService;
use App\Services\PurchaseAccessService;
use App\Services\UploadService;
use App\Support\ApiResponse;
use App\Support\MachineDesignFormConfig;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DesignController extends Controller
{
    public function __construct(
        private readonly UploadService $uploads,
        private readonly PurchaseAccessService $access,
        private readonly DesignImageMatchingService $imageMatching,
        private readonly DesignWatermarkService $watermarks,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'section' => ['sometimes', 'string', Rule::in(['latest', 'today', 'yesterday', 'tomorrow'])],
            'categoryId' => ['sometimes', 'string', 'max:120'],
            'machineTypeId' => ['sometimes', 'string', 'max:120'],
            'machine_id' => ['sometimes', 'nullable', 'string', Rule::exists('machines', 'public_id')->where('is_active', true)],
            'machine_type_id' => ['sometimes', 'nullable', 'string', Rule::exists('machine_types', 'public_id')->where('is_active', true)],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['string', 'distinct', Rule::exists('design_categories', 'public_id')->where('is_active', true)],
            'design_type_ids' => ['sometimes', 'array'],
            'design_type_ids.*' => ['string', 'distinct', Rule::exists('design_types', 'public_id')->where('is_active', true)],
            'stitch_ranges' => ['sometimes', 'array'],
            'stitch_ranges.*' => ['string', 'distinct', Rule::in($this->stitchRanges())],
            'needle_values' => ['sometimes', 'array'],
            'needle_values.*' => ['integer', 'distinct', 'between:0,12'],
            'area_ranges' => ['sometimes', 'array'],
            'area_ranges.*' => ['string', 'distinct', Rule::in($this->areaRanges())],
            'search' => ['sometimes', 'string', 'max:120'],
            'sort' => ['sometimes', 'string', Rule::in(['popular', 'newest', 'price-asc', 'price-desc'])],
        ]);
        $today = now(config('designhub.marketplace_timezone'))->toDateString();
        $yesterday = now(config('designhub.marketplace_timezone'))->subDay()->toDateString();
        $tomorrow = now(config('designhub.marketplace_timezone'))->addDay()->toDateString();

        $designs = Design::query()
            ->listed()
            ->forUserMachines($request->user())
            ->when(($filters['section'] ?? null) === 'today', fn ($query) => $query->scheduledFor($today))
            ->when(($filters['section'] ?? null) === 'yesterday', fn ($query) => $query->scheduledFor($yesterday))
            ->when(($filters['section'] ?? null) === 'tomorrow', fn ($query) => $query->scheduledFor($tomorrow))
            ->when(! in_array(($filters['section'] ?? null), ['today', 'yesterday', 'tomorrow'], true), fn ($query) => $query->published($today))
            ->when($filters['categoryId'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['machineTypeId'] ?? null, fn ($query, $machineTypeId) => $query->where('machine_type_id', $machineTypeId))
            ->when($filters['machine_id'] ?? null, fn ($query, $machineId) => $query->where(function ($machineFilter) use ($machineId) {
                $machineFilter
                    ->whereHas('machine', fn ($machineQuery) => $machineQuery->where('public_id', $machineId))
                    ->orWhere(function ($legacyQuery) use ($machineId) {
                        $legacyQuery
                            ->whereNull('machine_id')
                            ->whereHas('machineType.machine', fn ($machineQuery) => $machineQuery->where('public_id', $machineId));
                    });
            }))
            ->when($filters['machine_type_id'] ?? null, fn ($query, $machineTypeId) => $query->where('machine_type_id', $machineTypeId))
            ->when($filters['category_ids'] ?? [], fn ($query, $categoryIds) => $query->whereIn('category_id', $categoryIds))
            ->when($this->hasVariantFilters($filters), fn ($query) => $query->whereHas(
                'variants',
                fn ($variantQuery) => $this->applyVariantFilters($variantQuery, $filters),
            ))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', '%'.$search.'%')
                        ->orWhere('file_name', 'like', '%'.$search.'%');
                });
            })
            ->with(['owner', 'type.category', 'fileType', 'variants.type'])
            ->when(($filters['sort'] ?? 'newest') === 'popular', fn ($query) => $query
                ->withCount(['purchases as paid_purchase_count' => fn ($purchaseQuery) => $purchaseQuery->where('status', 'paid')])
                ->orderByDesc('paid_purchase_count')
                ->orderByDesc('publish_date')
                ->orderByDesc('created_at'))
            ->when(($filters['sort'] ?? 'newest') === 'newest', fn ($query) => $query
                ->orderByDesc('publish_date')
                ->orderByDesc('created_at'))
            ->when(($filters['sort'] ?? null) === 'price-asc', fn ($query) => $query
                ->orderBy('price_paise')
                ->orderByDesc('publish_date')
                ->orderByDesc('created_at'))
            ->when(($filters['sort'] ?? null) === 'price-desc', fn ($query) => $query
                ->orderByDesc('price_paise')
                ->orderByDesc('publish_date')
                ->orderByDesc('created_at'))
            ->orderByDesc('id')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated(
            $designs,
            $designs->getCollection()->map(
                fn (Design $design) => (new DesignResource($design))->resolve($request)
            )->values(),
            'Designs retrieved successfully.',
        );
    }

    private function hasVariantFilters(array $filters): bool
    {
        return collect(['design_type_ids', 'stitch_ranges', 'needle_values', 'area_ranges'])
            ->contains(fn (string $key) => ($filters[$key] ?? []) !== []);
    }

    private function applyVariantFilters($query, array $filters): void
    {
        $query
            ->when($filters['design_type_ids'] ?? [], fn ($variantQuery, $ids) => $variantQuery
                ->whereHas('type', fn ($typeQuery) => $typeQuery->whereIn('public_id', $ids)))
            ->when($filters['stitch_ranges'] ?? [], fn ($variantQuery, $ranges) => $this->applyRanges(
                $variantQuery,
                'stitch_count',
                $ranges,
                inclusiveFinalUpper: 250000,
            ))
            ->when($filters['needle_values'] ?? [], fn ($variantQuery, $values) => $variantQuery->whereIn('needle', $values))
            ->when($filters['area_ranges'] ?? [], fn ($variantQuery, $ranges) => $this->applyRanges(
                $variantQuery,
                'area',
                $ranges,
            ));
    }

    private function applyRanges($query, string $column, array $ranges, ?int $inclusiveFinalUpper = null): void
    {
        $query->where(function ($rangeQuery) use ($column, $ranges, $inclusiveFinalUpper) {
            foreach ($ranges as $range) {
                [$minimum, $maximum] = explode(':', $range, 2);
                $rangeQuery->orWhere(function ($singleRange) use ($column, $minimum, $maximum, $inclusiveFinalUpper) {
                    $singleRange->where($column, '>=', (int) $minimum);

                    if ($maximum !== '*') {
                        $operator = $inclusiveFinalUpper !== null && (int) $maximum === $inclusiveFinalUpper ? '<=' : '<';
                        $singleRange->where($column, $operator, (int) $maximum);
                    }
                });
            }
        });
    }

    /** @return list<string> */
    private function stitchRanges(): array
    {
        return collect(range(0, 225000, 25000))
            ->map(fn (int $minimum) => $minimum.':'.($minimum + 25000))
            ->all();
    }

    /** @return list<string> */
    private function areaRanges(): array
    {
        return ['0:100', '100:200', '200:300', '300:400', '400:500', '500:*'];
    }

    public function show(Request $request, string $designId)
    {
        $design = Design::query()
            ->listed()
            ->when($request->user() === null, fn ($query) => $query->published())
            ->with('owner')
            ->where('public_id', $designId)
            ->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        return ApiResponse::data(
            (new DesignResource($design))->resolve($request),
            message: 'Design retrieved successfully.',
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validatedDesignPayload($request);
        $this->validateStoredPreviewUniqueness($validated['images']);

        $design = DB::transaction(function () use ($request, $validated) {
            $design = Design::query()->create([
                'user_id' => $request->user()->id,
                'design_type_id' => $validated['design_type_id'],
                'design_file_type_id' => $validated['design_file_type_id'],
                'machine_id' => $validated['machine_id'],
                'machine_type_id' => $validated['machineTypeId'],
                'category_id' => $validated['categoryId'],
                'show_price' => $validated['showPrice'],
                'publish_date' => $validated['publishDate'],
                'details' => $validated['details'],
                'images' => $validated['images'],
                'files' => $validated['files'],
                'title' => $validated['title'],
                'price_paise' => $validated['pricePaise'],
                'size' => $validated['size'],
                'color' => $validated['color'],
                'sequence_type' => $validated['sequenceType'],
                'preview_path' => $validated['preview_path'],
                'stitch_path' => $validated['stitch_path'],
                'file_name' => $validated['fileName'],
            ]);

            $design->variants()->createMany($validated['variants']);

            return $design;
        });

        $design->load(['owner', 'type.category', 'fileType', 'variants.type']);
        $this->watermarks->syncDesign($design);
        $design->refresh();
        $this->imageMatching->syncDesign($design);

        return ApiResponse::data(
            (new DesignResource($design))->resolve($request),
            201,
            'Design created successfully.',
        );
    }

    public function update(Request $request, string $designId)
    {
        $design = Design::query()->listed()->where('public_id', $designId)->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        if ($design->user_id !== $request->user()->id) {
            return ApiResponse::error('You cannot edit this design.', 'design_update_forbidden', 403);
        }

        $validated = $this->validatedDesignPayload($request, $design);

        if ($request->hasAny(['images', 'imageUri'])) {
            $this->validateStoredPreviewUniqueness($validated['images'], $design);
        }

        DB::transaction(function () use ($design, $validated) {
            $design->update([
                'design_type_id' => $validated['design_type_id'],
                'design_file_type_id' => $validated['design_file_type_id'],
                'machine_id' => $validated['machine_id'],
                'machine_type_id' => $validated['machineTypeId'],
                'category_id' => $validated['categoryId'],
                'show_price' => $validated['showPrice'],
                'publish_date' => $validated['publishDate'],
                'details' => $validated['details'],
                'images' => $validated['images'],
                'files' => $validated['files'],
                'title' => $validated['title'],
                'price_paise' => $validated['pricePaise'],
                'size' => $validated['size'],
                'color' => $validated['color'],
                'sequence_type' => $validated['sequenceType'],
                'preview_path' => $validated['preview_path'],
                'stitch_path' => $validated['stitch_path'],
                'file_name' => $validated['fileName'],
            ]);

            $design->variants()->delete();
            $design->variants()->createMany($validated['variants']);
        });

        $design->load(['owner', 'type.category', 'fileType', 'variants.type']);
        $this->watermarks->syncDesign($design);
        $design->refresh();
        $this->imageMatching->syncDesign($design);

        return ApiResponse::data(
            (new DesignResource($design))->resolve($request),
            message: 'Design updated successfully.',
        );
    }

    public function destroy(Request $request, string $designId)
    {
        $design = Design::query()->listed()->where('public_id', $designId)->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        if ($design->user_id !== $request->user()->id) {
            return ApiResponse::error('You cannot delete this design.', 'design_delete_forbidden', 403);
        }

        $design->update(['deleted_at' => now()]);
        $this->imageMatching->removeDesign($design);

        return ApiResponse::success('Design deleted successfully.');
    }

    /** @param list<array{path: string}> $images */
    private function validateStoredPreviewUniqueness(array $images, ?Design $ignore = null): void
    {
        // Keep compatibility with legacy clients and contract fixtures that can
        // submit an upload URL before its local file has been materialized.
        // The indexing service reports unavailable files separately.
        $images = collect($images)
            ->filter(fn (array $image): bool => filled($image['path'] ?? null)
                && Storage::disk('public')->exists($image['path']))
            ->values()
            ->all();

        if ($images === []) {
            return;
        }

        try {
            $this->imageMatching->assertStoredImagesAreUnique($images, $ignore);
        } catch (DuplicateDesignImageException $exception) {
            $match = $exception->matchedDesignId ? ' Existing design: '.$exception->matchedDesignId.'.' : '';

            throw ValidationException::withMessages([
                'images' => sprintf(
                    'This image is already used or is too similar to an existing design (%.2f%% match).%s Upload a different image.',
                    $exception->similarityScore,
                    $match,
                ),
            ]);
        } catch (ImageMatchingUnavailableException) {
            throw ValidationException::withMessages([
                'images' => 'Duplicate image validation is temporarily unavailable. Please try again.',
            ]);
        }
    }

    public function download(Request $request, string $designId)
    {
        $design = Design::query()->listed()->where('public_id', $designId)->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        if (! $this->access->canDownloadStitch($request->user(), $design)) {
            return ApiResponse::error(
                'Purchase this design to download the stitch file.',
                'design_download_forbidden',
                403,
            );
        }

        $fileIndex = max($request->integer('file', 0), 0);
        $file = $this->designFile($design, $fileIndex);

        if ($file === null) {
            return ApiResponse::error('Stitch file is unavailable.', 'stitch_file_not_found', 404);
        }

        $expiresAt = now()->addMinutes((int) config('designhub.download_url_ttl_minutes'));
        $url = URL::temporarySignedRoute(
            'api.v1.files.stitch',
            $expiresAt,
            ['design' => $design->public_id, 'file' => $fileIndex]
        );

        return ApiResponse::data([
            'url' => $url,
            'fileName' => $file['name'],
            'expiresAt' => $expiresAt->utc()->toIso8601String(),
        ], message: 'Download URL created successfully.');
    }

    public function streamStitch(Request $request, string $designId)
    {
        $design = Design::query()->listed()->where('public_id', $designId)->firstOrFail();

        $file = $this->designFile($design, max($request->integer('file', 0), 0));

        if ($file === null) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $file['path'],
            $file['name'],
        );
    }

    public function downloadImage(Request $request, string $designId, int $imageIndex)
    {
        $design = Design::query()->listed()->where('public_id', $designId)->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        if (! $this->access->canDownloadStitch($request->user(), $design)) {
            return ApiResponse::error(
                'Purchase this design to download its original image.',
                'design_image_download_forbidden',
                403,
            );
        }

        $image = $this->designImage($design, max($imageIndex, 0));

        if ($image === null || ! Storage::disk('public')->exists($image['path'])) {
            return ApiResponse::error('Original design image is unavailable.', 'design_image_not_found', 404);
        }

        $expiresAt = now()->addMinutes((int) config('designhub.download_url_ttl_minutes'));

        return ApiResponse::data([
            'url' => URL::temporarySignedRoute(
                'api.v1.files.design-images',
                $expiresAt,
                ['designId' => $design->public_id, 'imageIndex' => $imageIndex],
            ),
            'fileName' => $image['name'],
            'expiresAt' => $expiresAt->utc()->toIso8601String(),
        ], message: 'Original image download URL created successfully.');
    }

    public function streamImage(string $designId, int $imageIndex)
    {
        $design = Design::query()->listed()->where('public_id', $designId)->firstOrFail();
        $image = $this->designImage($design, max($imageIndex, 0));

        abort_if($image === null, 404);
        abort_unless(Storage::disk('public')->exists($image['path']), 404);

        return Storage::disk('public')->download($image['path'], $image['name'], [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function validatedDesignPayload(Request $request, ?Design $existingDesign = null): array
    {
        $machine = $this->resolvePayloadMachine($request, $existingDesign);
        $config = $machine?->normalizedDesignFormConfig() ?? MachineDesignFormConfig::defaults();
        $required = $config['required'];
        $specificationFields = $config['specificationFields'];
        $specificationRequired = $specificationFields['required'];
        $allowedSpecificationModes = collect($config['specificationModes'])
            ->filter()
            ->keys()
            ->map(fn (string $mode) => $mode === 'designProperty' ? 'design_property' : 'technical')
            ->values()
            ->all();

        $validated = $request->validate([
            'machineId' => ['sometimes', 'nullable', 'string', Rule::exists('machines', 'public_id')->where('is_active', true)],
            'machineTypeId' => [$config['machineType'] && $required['machineType'] ? 'required' : 'nullable', 'string', 'max:120'],
            'categoryId' => [$config['category'] && $required['category'] ? 'required' : 'nullable', 'string', 'max:120'],
            'showPrice' => ['sometimes', 'boolean'],
            'publishDate' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'title' => [! $config['title'] ? 'exclude' : ($required['title'] ? 'required' : 'nullable'), 'string', 'min:1', 'max:120'],
            'pricePaise' => [$config['pricing'] && $required['pricing'] ? 'required' : 'nullable', 'integer', 'min:'.($config['pricing'] ? 100 : 0), 'max:100000000'],
            'sequenceType' => [$config['sequenceType'] && $required['sequenceType'] ? 'required' : 'sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'size' => ['sometimes', 'nullable', 'string', 'max:120'],
            'color' => ['sometimes', 'nullable', 'string', 'max:120'],
            'imageUri' => [$config['previewImages'] && $required['previewImages'] ? 'required_without:images' : 'sometimes', 'nullable', 'string'],
            'fileName' => [$config['stitchFiles'] && $required['stitchFiles'] ? 'required_without:files' : 'sometimes', 'nullable', 'string', 'max:255'],
            'fileUri' => [$config['stitchFiles'] && $required['stitchFiles'] ? 'required_without:files' : 'sometimes', 'nullable', 'string'],
            'typeId' => [$config['designTypes'] && $required['designTypes'] ? 'required_without:designTypes' : 'sometimes', 'nullable', 'string', 'max:120'],
            'designTypes' => [$config['designTypes'] && $required['designTypes'] ? 'required_without:typeId' : 'sometimes', 'nullable', 'array', 'min:1', 'max:20'],
            'designTypes.*.typeId' => ['nullable', 'string', 'max:120'],
            'designTypes.*.customTypeName' => ['nullable', 'string', 'min:1', 'max:120'],
            'designTypes.*.specificationMode' => ['sometimes', Rule::in($allowedSpecificationModes)],
            'designTypes.*.details' => [$config['designSpecifications'] && $required['designSpecifications'] ? 'required' : 'sometimes', 'array'],
            'designTypes.*.details.image' => [$config['designSpecifications'] && $specificationFields['image'] ? 'sometimes' : 'exclude', 'nullable', 'array'],
            'designTypes.*.details.image.name' => ['required_with:designTypes.*.details.image', 'string', 'max:255'],
            'designTypes.*.details.image.uri' => ['required_with:designTypes.*.details.image', 'string', 'max:2048'],
            'designTypes.*.details.removeImage' => ['sometimes', 'boolean'],
            'designTypes.*.details.nameOrNumber' => [$config['designSpecifications'] && $specificationFields['nameOrNumber'] ? 'sometimes' : 'exclude', 'string', 'max:120'],
            'designTypes.*.details.stitchCount' => [$config['designSpecifications'] && $specificationFields['stitchCount'] ? 'sometimes' : 'exclude', 'integer', 'min:0'],
            'designTypes.*.details.needle' => [$config['designSpecifications'] && $specificationFields['needle'] ? 'sometimes' : 'exclude', 'integer', 'min:0'],
            'designTypes.*.details.sequenceType' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'designTypes.*.details.area' => [$config['designSpecifications'] && $specificationFields['area'] ? 'sometimes' : 'exclude', 'numeric', 'min:0', 'max:9999999999.99'],
            'designTypes.*.details.height' => [$config['designSpecifications'] && $specificationFields['height'] ? 'sometimes' : 'exclude', 'numeric', 'min:0', 'max:9999999999.99'],
            'designTypes.*.details.width' => [$config['designSpecifications'] && $specificationFields['width'] ? 'sometimes' : 'exclude', 'numeric', 'min:0', 'max:9999999999.99'],
            'designFileTypeId' => [
                $config['designFileType'] && $required['designFileType'] ? 'required' : 'nullable',
                'string',
                Rule::exists('design_file_types', 'public_id')->where('is_active', true),
            ],
            'details' => ['sometimes', 'array', 'min:1', 'max:50'],
            'details.*.names' => ['required_with:details', 'array', 'min:1', 'max:20'],
            'details.*.names.*' => ['required', 'string', 'max:120'],
            'details.*.stitch' => ['required_with:details', 'string', 'max:30'],
            'details.*.needle' => ['required_with:details', 'integer', 'min:0'],
            'details.*.sequenceType' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'details.*.area' => ['required_with:details', 'string', 'max:120'],
            'details.*.height' => ['required_with:details', 'string', 'max:30'],
            'details.*.width' => ['required_with:details', 'string', 'max:30'],
            'images' => ['sometimes', 'array', 'min:1', 'max:20'],
            'images.*.name' => ['required_with:images', 'string', 'max:255'],
            'images.*.uri' => ['required_with:images', 'string', 'max:2048'],
            'files' => ['sometimes', 'array', 'min:1', 'max:20'],
            'files.*.name' => ['required_with:files', 'string', 'max:255'],
            'files.*.uri' => ['required_with:files', 'string', 'max:2048'],
        ]);

        if ($machine === null) {
            throw ValidationException::withMessages([
                'machineId' => 'Select the machine used for this design.',
            ]);
        }

        $catalogTypeIds = collect($validated['designTypes'] ?? [])
            ->pluck('typeId')
            ->filter()
            ->values();
        $legacySequenceType = collect($validated['designTypes'] ?? [])
            ->map(fn (array $designType) => data_get($designType, 'details.sequenceType'))
            ->first(fn ($value) => $value !== null);
        $sequenceType = $validated['sequenceType']
            ?? $existingDesign?->sequence_type
            ?? $legacySequenceType
            ?? data_get($validated, 'details.0.sequenceType')
            ?? 0;
        $customTypeNames = collect($validated['designTypes'] ?? [])
            ->pluck('customTypeName')
            ->filter()
            ->map(fn (string $name) => mb_strtolower(trim($name)))
            ->values();

        foreach ($validated['designTypes'] ?? [] as $index => $designType) {
            $hasCatalogType = filled($designType['typeId'] ?? null);
            $hasCustomType = filled($designType['customTypeName'] ?? null);

            if ($hasCatalogType === $hasCustomType) {
                throw ValidationException::withMessages([
                    'designTypes.'.$index.'.customTypeName' => 'Send either typeId or customTypeName for each design type.',
                ]);
            }
        }

        if ($catalogTypeIds->duplicates()->isNotEmpty() || $customTypeNames->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'designTypes' => 'Each catalog or custom design type may only be added once.',
            ]);
        }

        $selection = $this->resolveMachineSelection($validated, $machine, $config, $catalogTypeIds);

        $designFileType = isset($validated['designFileTypeId'])
            ? DesignFileType::query()
                ->where('public_id', $validated['designFileTypeId'])
                ->where('is_active', true)
                ->first()
            : null;

        if ($designFileType !== null && ! $designFileType->machines()->where('machines.id', $machine->id)->exists()) {
            throw ValidationException::withMessages([
                'designFileTypeId' => 'The selected file type is not available for this machine.',
            ]);
        }

        $images = [];
        $files = [];

        try {
            $imageAttachments = $validated['images'] ?? (filled($validated['imageUri'] ?? null) ? [[
                'name' => basename(parse_url($validated['imageUri'], PHP_URL_PATH) ?: 'preview.jpg'),
                'uri' => $validated['imageUri'],
            ]] : []);
            if ($config['previewImages'] && $imageAttachments !== []) {
                $images = $this->resolveAttachments(
                    $request,
                    $imageAttachments,
                    'photo',
                    'images',
                );
            }

            $fileAttachments = $validated['files'] ?? (filled($validated['fileUri'] ?? null) ? [[
                'name' => $validated['fileName'] ?? basename(parse_url($validated['fileUri'], PHP_URL_PATH) ?: 'design.dst'),
                'uri' => $validated['fileUri'],
            ]] : []);
            if ($config['stitchFiles'] && $fileAttachments !== []) {
                $files = $this->resolveAttachments(
                    $request,
                    $fileAttachments,
                    'stitch',
                    'files',
                );
            }
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['imageUri' => $exception->getMessage()]);
        }

        if ($config['previewImages'] && $required['previewImages'] && $images === []) {
            throw ValidationException::withMessages(['imageUri' => 'At least one preview image is required.']);
        }

        if ($config['stitchFiles'] && $required['stitchFiles'] && $files === []) {
            throw ValidationException::withMessages(['fileUri' => 'At least one stitch file is required.']);
        }

        if ($config['designTypes'] && isset($validated['designTypes'])) {
            [$variants, $details] = $this->resolveDesignTypeVariants(
                $request,
                $validated['designTypes'],
                $selection['category_id'],
                $config['designSpecifications'] ? $specificationFields : null,
                $specificationRequired,
                $allowedSpecificationModes,
                $validated['title'] ?? null,
                $existingDesign,
            );
        } elseif ($config['designTypes'] && filled($validated['typeId'] ?? null)) {
            [$variants, $details] = $this->resolveLegacyVariant(
                $validated,
                $selection['design_type_id'],
            );
        } else {
            $variants = [];
            $details = [];
        }

        $details = collect($details)->map(function (array $detail) {
            unset($detail['sequenceType']);

            return $detail;
        })->values()->all();

        $explicitTitle = $config['title'] ? trim((string) ($validated['title'] ?? '')) : '';
        $automaticTitle = trim((string) ($variants[0]['name_or_number'] ?? ''));

        if ($automaticTitle === '' && filled($images[0]['name'] ?? null)) {
            $automaticTitle = trim((string) pathinfo($images[0]['name'], PATHINFO_FILENAME));
        }

        $automaticTitle = mb_substr($automaticTitle !== '' ? $automaticTitle : 'Untitled design', 0, 120);
        $resolvedTitle = mb_substr($explicitTitle !== '' ? $explicitTitle : $automaticTitle, 0, 120);
        $firstVariant = $variants[0] ?? [
            'name_or_number' => $resolvedTitle,
            'height' => 0,
            'width' => 0,
        ];
        $summarySize = $config['designSpecifications'] && isset($validated['designTypes'])
            ? $firstVariant['height'].' × '.$firstVariant['width']
            : (($details[0]['height'] ?? '') !== '' && ($details[0]['width'] ?? '') !== ''
                ? trim($details[0]['height']).' × '.trim($details[0]['width'])
                : trim((string) ($validated['size'] ?? '')));

        return [
            'design_type_id' => $selection['design_type_id'],
            'design_file_type_id' => $designFileType?->id,
            'machine_id' => $machine->id,
            'machineTypeId' => $selection['machine_type_id'],
            'categoryId' => $selection['category_id'],
            'showPrice' => $config['pricing'] ? ($validated['showPrice'] ?? true) : false,
            'publishDate' => $validated['publishDate']
                ?? $existingDesign?->publish_date?->format('Y-m-d')
                ?? now(config('designhub.marketplace_timezone'))->toDateString(),
            'variants' => $variants,
            'details' => $details,
            'images' => $images,
            'files' => $files,
            'title' => $resolvedTitle,
            'pricePaise' => $config['pricing'] ? ($validated['pricePaise'] ?? 0) : 0,
            'sequenceType' => $config['sequenceType'] ? $sequenceType : 0,
            'size' => $summarySize,
            'color' => trim($validated['color'] ?? ''),
            'preview_path' => $images[0]['path'] ?? null,
            'stitch_path' => $files[0]['path'] ?? null,
            'fileName' => $files[0]['name'] ?? null,
        ];
    }

    private function resolvePayloadMachine(Request $request, ?Design $existingDesign): ?Machine
    {
        $input = $request->validate([
            'machineId' => ['sometimes', 'nullable', 'string', Rule::exists('machines', 'public_id')->where('is_active', true)],
            'machineTypeId' => ['sometimes', 'nullable', 'string', Rule::exists('machine_types', 'public_id')->where('is_active', true)],
        ]);

        $machineType = isset($input['machineTypeId'])
            ? MachineType::query()->with('machine')->where('public_id', $input['machineTypeId'])->first()
            : null;
        $machine = isset($input['machineId'])
            ? Machine::query()->where('public_id', $input['machineId'])->where('is_active', true)->first()
            : $machineType?->machine;

        if ($machine === null) {
            $candidateTypeId = $request->input('designTypes.0.typeId', $request->input('typeId'));
            $candidateType = filled($candidateTypeId)
                ? DesignType::query()
                    ->with('categories.machineTypes.machine')
                    ->where('public_id', $candidateTypeId)
                    ->where('is_active', true)
                    ->first()
                : null;
            if ($candidateType !== null) {
                $machine = $candidateType->categories
                    ->flatMap->machineTypes
                    ->first(fn (MachineType $type) => $type->is_active && $type->machine?->is_active)
                    ?->machine;
            }
        }

        if ($machine === null && filled($request->input('categoryId'))) {
            $category = DesignCategory::query()
                ->with('machineTypes.machine')
                ->where('public_id', $request->input('categoryId'))
                ->where('is_active', true)
                ->first();
            if ($category !== null) {
                $machine = $category->machineTypes
                    ->first(fn (MachineType $type) => $type->is_active && $type->machine?->is_active)
                    ?->machine;
            }
        }

        if ($machine === null && $existingDesign !== null) {
            $existingDesign->loadMissing(['machine', 'machineType.machine']);
            $machine = $existingDesign->machine ?? $existingDesign->machineType?->machine;
        }

        if ($machine !== null && ! $machine->is_active) {
            $machine = null;
        }

        if ($machineType !== null && $machine !== null && $machineType->machine_id !== $machine->id) {
            throw ValidationException::withMessages([
                'machineTypeId' => 'The selected machine type does not belong to this machine.',
            ]);
        }

        return $machine;
    }

    /** @return array{design_type_id: int|null, category_id: string|null, machine_type_id: string|null} */
    private function resolveMachineSelection(array $validated, Machine $machine, array $config, $catalogTypeIds): array
    {
        $machineType = null;

        if ($config['machineType'] && filled($validated['machineTypeId'] ?? null)) {
            $machineType = MachineType::query()
                ->where('public_id', $validated['machineTypeId'])
                ->where('is_active', true)
                ->first();

            if ($machineType === null || $machineType->machine_id !== $machine->id) {
                throw ValidationException::withMessages([
                    'machineTypeId' => 'The selected machine type does not belong to this machine.',
                ]);
            }
        }

        if (! $config['category'] || blank($validated['categoryId'] ?? null)) {
            if ($config['designTypes'] && ($catalogTypeIds->isNotEmpty() || filled($validated['typeId'] ?? null))) {
                throw ValidationException::withMessages([
                    'categoryId' => 'Select a category before adding design types.',
                ]);
            }

            return [
                'design_type_id' => null,
                'category_id' => null,
                'machine_type_id' => $machineType?->public_id,
            ];
        }

        $category = DesignCategory::query()
            ->where('public_id', $validated['categoryId'])
            ->where('is_active', true)
            ->first();

        $categoryIsCompatible = $category !== null && ($machineType !== null
            ? $category->machineTypes()->whereKey($machineType->id)->exists()
            : $category->machineTypes()
                ->where('machine_types.machine_id', $machine->id)
                ->where('machine_types.is_active', true)
                ->exists());

        if (! $categoryIsCompatible) {
            throw ValidationException::withMessages([
                'categoryId' => $machineType !== null
                    ? 'The selected category is not available for this machine type.'
                    : 'The selected category is not available for this machine.',
            ]);
        }

        if (! $config['designTypes']) {
            return [
                'design_type_id' => null,
                'category_id' => $category->public_id,
                'machine_type_id' => $machineType?->public_id,
            ];
        }

        $selectedTypeId = $catalogTypeIds->first() ?? ($validated['typeId'] ?? null);
        $designTypeId = null;

        if ($selectedTypeId !== null && $selectedTypeId !== $category->public_id) {
            $selectedType = DesignType::query()
                ->where('public_id', $selectedTypeId)
                ->where('is_active', true)
                ->whereHas('categories', fn ($query) => $query
                    ->where('design_categories.id', $category->id)
                    ->where('design_categories.is_active', true))
                ->first();

            if ($selectedType === null) {
                $field = isset($validated['designTypes']) ? 'designTypes.0.typeId' : 'typeId';

                throw ValidationException::withMessages([
                    $field => 'The selected type does not belong to this category.',
                ]);
            }

            $designTypeId = $selectedType->id;
        }

        return [
            'design_type_id' => $designTypeId,
            'category_id' => $category->public_id,
            'machine_type_id' => $machineType?->public_id,
        ];
    }

    private function specificationRule(array $config, array $fields, array $required, string $key, bool $honourRequired = true): string
    {
        if (! $config['designSpecifications'] || ! $fields[$key]) {
            return 'exclude';
        }

        return $honourRequired && ($required[$key] ?? false) ? 'required' : 'sometimes';
    }

    private function resolveDesignTypeVariants(
        Request $request,
        array $designTypes,
        string $categoryId,
        ?array $specificationFields = null,
        array $specificationRequired = [],
        array $allowedSpecificationModes = ['technical'],
        ?string $fallbackTitle = null,
        ?Design $existingDesign = null,
    ): array {
        $types = DesignType::query()
            ->with('categories')
            ->whereIn('public_id', collect($designTypes)->pluck('typeId')->filter())
            ->where('is_active', true)
            ->get()
            ->keyBy('public_id');

        $existingVariants = $existingDesign?->loadMissing('variants.type')->variants
            ->keyBy(fn ($variant) => $this->variantIdentity($variant->type?->public_id, $variant->custom_type_name))
            ?? collect();

        $variants = collect($designTypes)->map(function (array $item, int $index) use ($request, $types, $categoryId, $specificationFields, $specificationRequired, $allowedSpecificationModes, $fallbackTitle, $existingVariants) {
            $typeId = $item['typeId'] ?? null;
            $type = $typeId ? $types->get($typeId) : null;

            if ($typeId && $type === null) {
                throw ValidationException::withMessages([
                    'designTypes.'.$index.'.typeId' => 'Selected type is invalid or inactive.',
                ]);
            }

            $belongsToCategory = ! $typeId || $type->categories->contains(
                fn (DesignCategory $category) => $category->public_id === $categoryId && $category->is_active,
            );

            if (! $belongsToCategory) {
                throw ValidationException::withMessages([
                    'designTypes.'.$index.'.typeId' => 'The selected type does not belong to this category.',
                ]);
            }

            $details = $item['details'] ?? [];
            $hasSpecifications = $specificationFields !== null && $details !== [];
            $fallbackName = trim((string) ($fallbackTitle ?: ($type?->name ?? $item['customTypeName'] ?? 'Design')));
            $existingVariant = $existingVariants->get($this->variantIdentity($typeId, $item['customTypeName'] ?? null));
            $modeWasExplicit = array_key_exists('specificationMode', $item);
            $designPropertyRequired = (bool) ($specificationRequired['image'] ?? false);
            $defaultMode = $designPropertyRequired || ! in_array('technical', $allowedSpecificationModes, true)
                ? 'design_property'
                : 'technical';
            $specificationMode = $item['specificationMode'] ?? $existingVariant?->specification_mode ?? $defaultMode;
            $designPropertyMode = $specificationMode === 'design_property';
            $legacyImageInput = ! $modeWasExplicit && (isset($details['image']) || array_key_exists('removeImage', $details));
            $imageEnabled = $specificationFields !== null
                && ($designPropertyMode || $legacyImageInput)
                && $specificationFields['image'];
            $removeImage = $imageEnabled && (bool) ($details['removeImage'] ?? false);
            $imagePath = $imageEnabled && ! $removeImage ? $existingVariant?->image_path : null;
            $imageName = $imageEnabled && ! $removeImage ? $existingVariant?->image_name : null;

            if ($imageEnabled && isset($details['image'])) {
                try {
                    $imagePath = $this->uploads->resolveStoragePath($request->user(), $details['image']['uri'], 'photo');
                    $imageName = trim($details['image']['name']);
                } catch (\InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([
                        'designTypes.'.$index.'.details.image.uri' => $exception->getMessage(),
                    ]);
                }
            }

            if ($designPropertyMode && $imageEnabled && blank($imagePath)) {
                throw ValidationException::withMessages([
                    'designTypes.'.$index.'.details.image' => 'Upload one image for this design type.',
                ]);
            }

            if ($specificationFields !== null && ($specificationRequired['nameOrNumber'] ?? false) && blank($details['nameOrNumber'] ?? null)) {
                throw ValidationException::withMessages([
                    'designTypes.'.$index.'.details.nameOrNumber' => 'Enter a name or number for this design type.',
                ]);
            }

            if (! $designPropertyMode && $specificationFields !== null) {
                foreach (['stitchCount', 'needle', 'area', 'height', 'width'] as $field) {
                    if ($specificationFields[$field] && ($specificationRequired[$field] ?? false) && ! array_key_exists($field, $details)) {
                        throw ValidationException::withMessages([
                            'designTypes.'.$index.'.details.'.$field => 'This technical specification is required.',
                        ]);
                    }
                }
            }

            return [
                'design_type_id' => $type?->id,
                'custom_type_name' => $typeId ? null : trim($item['customTypeName']),
                'specification_mode' => $specificationMode,
                'image_path' => $imagePath,
                'image_name' => $imageName,
                'name_or_number' => $hasSpecifications && $specificationFields['nameOrNumber']
                    ? trim((string) ($details['nameOrNumber'] ?? $fallbackName))
                    : $fallbackName,
                'stitch_count' => $hasSpecifications && ! $designPropertyMode && $specificationFields['stitchCount'] ? ($details['stitchCount'] ?? 0) : 0,
                'needle' => $hasSpecifications && ! $designPropertyMode && $specificationFields['needle'] ? ($details['needle'] ?? 0) : 0,
                'area' => $hasSpecifications && ! $designPropertyMode && $specificationFields['area'] ? ($details['area'] ?? 0) : 0,
                'height' => $hasSpecifications && ! $designPropertyMode && $specificationFields['height'] ? ($details['height'] ?? 0) : 0,
                'width' => $hasSpecifications && ! $designPropertyMode && $specificationFields['width'] ? ($details['width'] ?? 0) : 0,
                'sort_order' => $index,
            ];
        })->values()->all();

        return [$variants, $this->legacyDetailsFromVariants($variants)];
    }

    private function variantIdentity(?string $typeId, ?string $customTypeName): string
    {
        return filled($typeId)
            ? 'type:'.$typeId
            : 'custom:'.mb_strtolower(trim((string) $customTypeName));
    }

    private function resolveLegacyVariant(array $validated, ?int $designTypeId): array
    {
        $details = $validated['details'] ?? [[
            'names' => [$validated['title']],
            'stitch' => '0',
            'needle' => 0,
            'area' => $validated['size'],
            'height' => '0',
            'width' => '0',
        ]];
        $detail = $details[0];
        $variant = [
            'design_type_id' => $designTypeId,
            'specification_mode' => 'technical',
            'name_or_number' => trim($detail['names'][0] ?? $validated['title']),
            'stitch_count' => is_numeric($detail['stitch'] ?? null) ? (int) $detail['stitch'] : 0,
            'needle' => (int) ($detail['needle'] ?? 0),
            'area' => is_numeric($detail['area'] ?? null) ? $detail['area'] : 0,
            'height' => is_numeric($detail['height'] ?? null) ? $detail['height'] : 0,
            'width' => is_numeric($detail['width'] ?? null) ? $detail['width'] : 0,
            'sort_order' => 0,
        ];

        return [$designTypeId === null ? [] : [$variant], $details];
    }

    private function legacyDetailsFromVariants(array $variants): array
    {
        return collect($variants)->map(fn (array $variant) => [
            'customTypeName' => $variant['custom_type_name'] ?? null,
            'names' => [$variant['name_or_number']],
            'stitch' => (string) $variant['stitch_count'],
            'needle' => $variant['needle'],
            'area' => (string) $variant['area'],
            'height' => (string) $variant['height'],
            'width' => (string) $variant['width'],
        ])->values()->all();
    }

    private function resolveAttachments(
        Request $request,
        array $attachments,
        string $kind,
        string $field,
    ): array {
        return collect($attachments)->map(function (array $attachment, int $index) use ($request, $kind, $field) {
            try {
                $path = $this->uploads->resolveStoragePath($request->user(), $attachment['uri'], $kind);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    $field.'.'.$index.'.uri' => $exception->getMessage(),
                ]);
            }

            return [
                'name' => trim($attachment['name']),
                'path' => $path,
            ];
        })->values()->all();
    }

    private function designFile(Design $design, int $index): ?array
    {
        $files = $design->files;
        if (is_array($files) && isset($files[$index]['path'])) {
            return [
                'name' => $files[$index]['name'] ?? 'design.dst',
                'path' => $files[$index]['path'],
            ];
        }

        if ($index === 0 && $design->stitch_path !== null) {
            return [
                'name' => $design->file_name ?? 'design.dst',
                'path' => $design->stitch_path,
            ];
        }

        return null;
    }

    /** @return array{name: string, path: string}|null */
    private function designImage(Design $design, int $index): ?array
    {
        $images = $design->images;

        if (is_array($images) && isset($images[$index]['path'])) {
            return [
                'name' => $images[$index]['name'] ?? basename($images[$index]['path']),
                'path' => $images[$index]['path'],
            ];
        }

        if ($index === 0 && $design->preview_path !== null) {
            return ['name' => basename($design->preview_path), 'path' => $design->preview_path];
        }

        return null;
    }
}
