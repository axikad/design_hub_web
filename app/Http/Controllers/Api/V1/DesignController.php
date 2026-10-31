<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\DesignFileType;
use App\Models\DesignType;
use App\Models\Machine;
use App\Models\MachineType;
use App\Services\DesignImageMatchingService;
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
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'section' => ['sometimes', 'string', Rule::in(['latest', 'today', 'tomorrow'])],
            'categoryId' => ['sometimes', 'string', 'max:120'],
            'machineTypeId' => ['sometimes', 'string', 'max:120'],
            'search' => ['sometimes', 'string', 'max:120'],
        ]);
        $today = now(config('designhub.marketplace_timezone'))->toDateString();
        $tomorrow = now(config('designhub.marketplace_timezone'))->addDay()->toDateString();

        $designs = Design::query()
            ->listed()
            ->forUserMachines($request->user())
            ->when(($filters['section'] ?? null) === 'today', fn ($query) => $query->scheduledFor($today))
            ->when(($filters['section'] ?? null) === 'tomorrow', fn ($query) => $query->scheduledFor($tomorrow))
            ->when(! in_array(($filters['section'] ?? null), ['today', 'tomorrow'], true), fn ($query) => $query->published($today))
            ->when($filters['categoryId'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['machineTypeId'] ?? null, fn ($query, $machineTypeId) => $query->where('machine_type_id', $machineTypeId))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', '%'.$search.'%')
                        ->orWhere('file_name', 'like', '%'.$search.'%');
                });
            })
            ->with(['owner', 'type.category', 'fileType', 'variants.type'])
            ->orderByDesc('publish_date')
            ->latest('created_at')
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

    public function show(Request $request, string $designId)
    {
        $design = Design::query()->listed()->with('owner')->where('public_id', $designId)->first();

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

    private function validatedDesignPayload(Request $request, ?Design $existingDesign = null): array
    {
        $machine = $this->resolvePayloadMachine($request, $existingDesign);
        $config = $machine?->normalizedDesignFormConfig() ?? MachineDesignFormConfig::defaults();
        $required = $config['required'];
        $specificationFields = $config['specificationFields'];
        $specificationRequired = $specificationFields['required'];

        $validated = $request->validate([
            'machineId' => ['sometimes', 'nullable', 'string', Rule::exists('machines', 'public_id')->where('is_active', true)],
            'machineTypeId' => [$config['machineType'] && $required['machineType'] ? 'required' : 'nullable', 'string', 'max:120'],
            'categoryId' => [$config['category'] && $required['category'] ? 'required' : 'nullable', 'string', 'max:120'],
            'showPrice' => ['sometimes', 'boolean'],
            'publishDate' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'title' => ['sometimes', 'nullable', 'string', 'min:1', 'max:120'],
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
            'designTypes.*.details' => [$config['designSpecifications'] && $required['designSpecifications'] ? 'required' : 'sometimes', 'array'],
            'designTypes.*.details.nameOrNumber' => [$this->specificationRule($config, $specificationFields, $specificationRequired, 'nameOrNumber'), 'string', 'max:120'],
            'designTypes.*.details.stitchCount' => [$this->specificationRule($config, $specificationFields, $specificationRequired, 'stitchCount'), 'integer', 'min:0'],
            'designTypes.*.details.needle' => [$this->specificationRule($config, $specificationFields, $specificationRequired, 'needle'), 'integer', 'min:0'],
            'designTypes.*.details.sequenceType' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'designTypes.*.details.area' => [$this->specificationRule($config, $specificationFields, $specificationRequired, 'area'), 'numeric', 'min:0', 'max:9999999999.99'],
            'designTypes.*.details.height' => [$this->specificationRule($config, $specificationFields, $specificationRequired, 'height'), 'numeric', 'min:0', 'max:9999999999.99'],
            'designTypes.*.details.width' => [$this->specificationRule($config, $specificationFields, $specificationRequired, 'width'), 'numeric', 'min:0', 'max:9999999999.99'],
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
                $validated['designTypes'],
                $selection['category_id'],
                $config['designSpecifications'] ? $specificationFields : null,
                $validated['title'] ?? null,
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

        $automaticTitle = trim((string) ($validated['title'] ?? ''));

        if ($automaticTitle === '' && filled($images[0]['name'] ?? null)) {
            $automaticTitle = trim((string) pathinfo($images[0]['name'], PATHINFO_FILENAME));
        }

        $automaticTitle = mb_substr($automaticTitle !== '' ? $automaticTitle : 'Untitled design', 0, 120);
        $firstVariant = $variants[0] ?? [
            'name_or_number' => $automaticTitle,
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
            'title' => $firstVariant['name_or_number'],
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

    private function specificationRule(array $config, array $fields, array $required, string $key): string
    {
        if (! $config['designSpecifications'] || ! $fields[$key]) {
            return 'exclude';
        }

        return $required[$key] ? 'required' : 'sometimes';
    }

    private function resolveDesignTypeVariants(
        array $designTypes,
        string $categoryId,
        ?array $specificationFields = null,
        ?string $fallbackTitle = null,
    ): array {
        $types = DesignType::query()
            ->with('categories')
            ->whereIn('public_id', collect($designTypes)->pluck('typeId')->filter())
            ->where('is_active', true)
            ->get()
            ->keyBy('public_id');

        $variants = collect($designTypes)->map(function (array $item, int $index) use ($types, $categoryId, $specificationFields, $fallbackTitle) {
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

            return [
                'design_type_id' => $type?->id,
                'custom_type_name' => $typeId ? null : trim($item['customTypeName']),
                'name_or_number' => $hasSpecifications && $specificationFields['nameOrNumber']
                    ? trim((string) ($details['nameOrNumber'] ?? $fallbackName))
                    : $fallbackName,
                'stitch_count' => $hasSpecifications && $specificationFields['stitchCount'] ? ($details['stitchCount'] ?? 0) : 0,
                'needle' => $hasSpecifications && $specificationFields['needle'] ? ($details['needle'] ?? 0) : 0,
                'area' => $hasSpecifications && $specificationFields['area'] ? ($details['area'] ?? 0) : 0,
                'height' => $hasSpecifications && $specificationFields['height'] ? ($details['height'] ?? 0) : 0,
                'width' => $hasSpecifications && $specificationFields['width'] ? ($details['width'] ?? 0) : 0,
                'sort_order' => $index,
            ];
        })->values()->all();

        return [$variants, $this->legacyDetailsFromVariants($variants)];
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
}
