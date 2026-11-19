<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ImageMatchingUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\DesignFileType;
use App\Models\DesignType;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\User;
use App\Services\DesignImageMatchingService;
use App\Services\DesignPresenter;
use App\Services\DesignWatermarkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DesignAdminController extends Controller
{
    public function __construct(
        private readonly DesignPresenter $presenter,
        private readonly DesignImageMatchingService $imageMatching,
        private readonly DesignWatermarkService $watermarks,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        $ownContentOnly = $actor->mustScopeMarketplaceContentToSelf();
        $search = trim((string) $request->string('search'));
        $ownerId = $ownContentOnly ? $actor->public_id : (string) $request->string('owner');
        $machineId = (string) $request->string('machine');
        $fileTypeId = (string) $request->string('fileType');

        if ($request->boolean('imageSearch')) {
            $imageSearch = $request->session()->get('admin_design_image_search');

            if (is_array($imageSearch) && is_array($imageSearch['matches'] ?? null)) {
                return Inertia::render('admin/designs/index', $this->designIndexProps(
                    $this->imageSearchPaginator($imageSearch['matches'], $actor),
                    [
                        'search' => '',
                        'owner' => '',
                        'machine' => (string) ($imageSearch['machineId'] ?? ''),
                        'fileType' => '',
                    ],
                    true,
                    $actor,
                ));
            }
        } else {
            $request->session()->forget('admin_design_image_search');
        }

        $designs = Design::query()
            ->listed()
            ->with(['owner', 'type.category', 'machine', 'machineType.machine', 'fileType'])
            ->when($ownContentOnly, fn ($query) => $query->where('user_id', $actor->id))
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('public_id', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%"));
            }))
            ->when($ownerId !== '', fn ($query) => $query->whereHas('owner', fn ($ownerQuery) => $ownerQuery->where('public_id', $ownerId)))
            ->when($machineId !== '', fn ($query) => $query->where(function ($machineFilter) use ($machineId) {
                $machineFilter
                    ->whereHas('machine', fn ($machineQuery) => $machineQuery->where('public_id', $machineId))
                    ->orWhere(function ($legacyQuery) use ($machineId) {
                        $legacyQuery
                            ->whereNull('machine_id')
                            ->whereHas('machineType.machine', fn ($machineQuery) => $machineQuery->where('public_id', $machineId));
                    });
            }))
            ->when($fileTypeId !== '', fn ($query) => $query->whereHas('fileType', fn ($fileTypeQuery) => $fileTypeQuery->where('public_id', $fileTypeId)))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Design $design) => $this->designRow($design));

        return Inertia::render('admin/designs/index', $this->designIndexProps(
            $designs,
            [
                'search' => $search,
                'owner' => $ownerId,
                'machine' => $machineId,
                'fileType' => $fileTypeId,
            ],
            false,
            $actor,
        ));
    }

    public function searchByImage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'machineId' => ['nullable', 'string', Rule::exists('machines', 'public_id')->where('is_active', true)],
        ]);
        $contents = file_get_contents($request->file('image')->getRealPath());

        if ($contents === false) {
            return back()->withErrors(['image' => 'The selected image could not be read.']);
        }

        try {
            $matches = collect($this->imageMatching->search(
                $contents,
                $request->file('image')->getMimeType() ?: 'image/jpeg',
                ['machineId' => $validated['machineId'] ?? null],
                100,
            ));
        } catch (ImageMatchingUnavailableException) {
            return back()->withErrors(['image' => 'Image matching is temporarily unavailable. Please try again.']);
        }

        $request->session()->put('admin_design_image_search', [
            'matches' => $matches->values()->all(),
            'machineId' => $validated['machineId'] ?? '',
        ]);

        return redirect()->route('admin.designs.index', ['imageSearch' => 1]);
    }

    public function imageSearchFallback(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_design_image_search');

        return redirect()->route('admin.designs.index');
    }

    /** @return array<string, mixed> */
    private function designIndexProps(LengthAwarePaginator $designs, array $filters, bool $imageSearchActive, User $actor): array
    {
        return [
            'designs' => $designs,
            'owners' => $this->ownerOptions($actor),
            'machines' => Machine::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
            ])->values(),
            'fileTypes' => $this->fileTypeOptions(),
            'filters' => $filters,
            'imageSearchActive' => $imageSearchActive,
            'ownContentOnly' => $actor->mustScopeMarketplaceContentToSelf(),
        ];
    }

    /** @param list<array{designId: string, score: float}> $matches */
    private function imageSearchPaginator(array $matches, User $actor): LengthAwarePaginator
    {
        $matches = collect($matches);
        $designs = Design::query()
            ->listed()
            ->with(['owner', 'type.category', 'machine', 'machineType.machine', 'fileType'])
            ->when($actor->mustScopeMarketplaceContentToSelf(), fn ($query) => $query->where('user_id', $actor->id))
            ->whereIn('public_id', $matches->pluck('designId'))
            ->get()
            ->keyBy('public_id');

        $rows = $matches->map(function (array $match) use ($designs) {
            $design = $designs->get($match['designId']);

            return $design === null ? null : [
                ...$this->designRow($design),
                'similarityScore' => $match['score'],
            ];
        })->filter()->values();

        return new LengthAwarePaginator(
            $rows,
            $rows->count(),
            max($rows->count(), 1),
            1,
            ['path' => route('admin.designs.index')],
        );
    }

    /** @return array<string, mixed> */
    private function designRow(Design $design): array
    {
        $presented = $this->presenter->forUser($design, $design->owner);

        return array_merge($presented, [
            'ownerName' => $design->owner?->name,
            'machineName' => $design->machine?->name ?? $design->machineType?->machine?->name,
            'machineTypeName' => $design->machineType?->name,
            'thumbnailUri' => $presented['images'][0]['thumbnailUri'] ?? $presented['imageUri'],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/designs/form', [
            'design' => null,
            'owners' => $this->ownerOptions($request->user()),
            'canChooseSeller' => $request->user()->isSuperAdmin(),
            'catalog' => $this->catalogForForm(),
            'fileTypes' => $this->fileTypeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateDesign($request, true);

        $owner = User::query()->where('public_id', $validated['ownerId'])->firstOrFail();
        $validated['variants'] = $this->storeVariantImages($request, $owner, $validated['variants']);

        $previewImages = $validated['previewImagesEnabled'] ? $this->storePreviewImages($request, $owner) : [];
        $previewPath = $previewImages[0]['path'] ?? null;
        $stitchFiles = $validated['stitchFilesEnabled'] ? $this->storeStitchFiles($request, $owner) : [];
        $firstStitchFile = $stitchFiles[0] ?? null;

        $design = DB::transaction(function () use ($owner, $validated, $previewImages, $previewPath, $stitchFiles, $firstStitchFile) {
            $design = Design::query()->create([
                'user_id' => $owner->id,
                'design_type_id' => $validated['designTypeId'],
                'design_file_type_id' => $validated['designFileTypeId'],
                'machine_id' => $validated['machineDbId'],
                'machine_type_id' => $validated['machineTypeId'],
                'category_id' => $validated['categoryId'],
                'title' => $validated['title'],
                'price_paise' => $validated['pricePaise'],
                'size' => $validated['size'],
                'color' => '',
                'sequence_type' => $validated['sequenceType'],
                'details' => $validated['details'],
                'images' => $previewImages,
                'files' => $stitchFiles,
                'preview_path' => $previewPath,
                'stitch_path' => $firstStitchFile['path'] ?? null,
                'file_name' => $firstStitchFile['name'] ?? null,
            ]);

            $design->variants()->createMany($validated['variants']);

            return $design;
        });

        $this->watermarks->syncDesign($design);
        $this->imageMatching->syncDesign($design->fresh());

        return redirect()->route('admin.designs.index')->with('success', 'Design created.');
    }

    public function edit(Request $request, string $design): Response
    {
        $model = Design::query()
            ->listed()
            ->with(['owner', 'type.category', 'fileType', 'machine', 'machineType.machine', 'variants.type'])
            ->when($request->user()->mustScopeMarketplaceContentToSelf(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->where('public_id', $design)
            ->firstOrFail();

        $presented = $this->presenter->forUser($model, $model->owner);

        return Inertia::render('admin/designs/form', [
            'design' => [
                'id' => $model->public_id,
                'title' => $model->title,
                'ownerId' => $model->owner?->public_id,
                'categoryId' => $model->category_id ?? $model->type?->category?->public_id,
                'machineTypeId' => $model->machine_type_id,
                'machineId' => $model->machine?->public_id ?? $model->machineType?->machine?->public_id,
                'designFileTypeId' => $model->fileType?->public_id,
                'sequenceType' => $model->sequence_type,
                'priceRupees' => $model->price_paise !== null ? number_format($model->price_paise / 100, 2, '.', '') : '',
                'variants' => $this->variantsForForm($model),
                'files' => collect($this->existingStitchFiles($model))->map(fn (array $file) => [
                    'name' => $file['name'],
                ])->values()->all(),
                'images' => collect($presented['images'])->map(fn (array $image) => [
                    'name' => $image['name'],
                    'uri' => $image['uri'],
                    'thumbnailUri' => $image['thumbnailUri'],
                ])->values()->all(),
            ],
            'owners' => $this->ownerOptions($request->user()),
            'canChooseSeller' => $request->user()->isSuperAdmin(),
            'catalog' => $this->catalogForForm(),
            'fileTypes' => $this->fileTypeOptions($model->design_file_type_id),
        ]);
    }

    public function update(Request $request, string $design): RedirectResponse
    {
        $model = Design::query()->listed()->where('public_id', $design)->firstOrFail();
        abort_if($request->user()->mustScopeMarketplaceContentToSelf() && $model->user_id !== $request->user()->id, 404);
        $validated = $this->validateDesign($request, false, $model);

        $owner = User::query()->where('public_id', $validated['ownerId'])->firstOrFail();
        $oldVariantImagePaths = $model->variants()->pluck('image_path')->filter()->all();
        $validated['variants'] = $this->storeVariantImages($request, $owner, $validated['variants']);

        $previewImages = $this->existingPreviewImages($model);
        if ($validated['previewImagesEnabled'] && ($request->hasFile('previewImages') || $request->hasFile('previewImage'))) {
            Storage::disk('public')->delete(collect($previewImages)->pluck('path')->filter()->unique()->all());
            Storage::disk('public')->delete(collect($previewImages)->pluck('watermarkedPath')->filter()->unique()->all());
            $previewImages = $this->storePreviewImages($request, $owner);
        }

        $previewPath = $previewImages[0]['path'] ?? null;

        if ($validated['previewImagesEnabled'] && $validated['previewImagesRequired'] && $previewImages === []) {
            throw ValidationException::withMessages([
                'previewImages' => 'Upload at least one preview image.',
            ]);
        }

        $stitchFiles = $this->existingStitchFiles($model);
        if ($validated['stitchFilesEnabled'] && $request->hasFile('stitchFiles')) {
            Storage::disk('local')->delete(collect($stitchFiles)->pluck('path')->filter()->unique()->all());
            $stitchFiles = $this->storeStitchFiles($request, $owner);
        }

        if ($validated['stitchFilesEnabled'] && $validated['stitchFilesRequired'] && $stitchFiles === []) {
            throw ValidationException::withMessages([
                'stitchFiles' => 'Upload at least one stitch file.',
            ]);
        }

        $firstStitchFile = $stitchFiles[0] ?? null;

        DB::transaction(function () use ($model, $owner, $validated, $previewImages, $previewPath, $stitchFiles, $firstStitchFile) {
            $model->update([
                'user_id' => $owner->id,
                'design_type_id' => $validated['designTypeId'],
                'design_file_type_id' => $validated['designFileTypeId'],
                'machine_id' => $validated['machineDbId'],
                'machine_type_id' => $validated['machineTypeId'],
                'category_id' => $validated['categoryId'],
                'title' => $validated['title'],
                'price_paise' => $validated['pricePaise'],
                'size' => $validated['size'],
                'color' => '',
                'sequence_type' => $validated['sequenceType'],
                'details' => $validated['details'],
                'images' => $previewImages,
                'files' => $stitchFiles,
                'preview_path' => $previewPath,
                'stitch_path' => $firstStitchFile['path'] ?? null,
                'file_name' => $firstStitchFile['name'] ?? null,
            ]);

            $model->variants()->delete();
            $model->variants()->createMany($validated['variants']);
        });

        $currentVariantImagePaths = collect($validated['variants'])->pluck('image_path')->filter()->all();
        Storage::disk('public')->delete(array_values(array_diff($oldVariantImagePaths, $currentVariantImagePaths)));

        $this->watermarks->syncDesign($model->fresh());
        $this->imageMatching->syncDesign($model->fresh());

        return redirect()->route('admin.designs.index')->with('success', 'Design updated.');
    }

    public function destroy(Request $request, string $design): RedirectResponse
    {
        $model = Design::query()
            ->listed()
            ->when($request->user()->mustScopeMarketplaceContentToSelf(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->where('public_id', $design)
            ->firstOrFail();
        $model->update(['deleted_at' => now()]);
        $this->imageMatching->removeDesign($model);

        return redirect()->route('admin.designs.index')->with('success', 'Design removed from marketplace.');
    }

    /** @return list<array{id: string, label: string}> */
    private function ownerOptions(?User $actor = null): array
    {
        if ($actor !== null && ! $actor->isSuperAdmin()) {
            return [[
                'id' => $actor->public_id,
                'label' => $actor->name,
            ]];
        }

        return User::query()
            ->whereNotNull('phone_key')
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->public_id,
                'label' => trim($user->name.' · '.$user->country_code.$user->mobile_number),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function validateDesign(Request $request, bool $creating, ?Design $existingDesign = null): array
    {
        $request->merge([
            'ownerId' => $request->user()->isSuperAdmin()
                ? trim((string) $request->input('ownerId'))
                : ($existingDesign?->owner?->public_id ?? $request->user()->public_id),
        ]);

        $machineInput = $request->validate([
            'machineId' => ['required', 'string', Rule::exists('machines', 'public_id')->where('is_active', true)],
        ]);
        $machine = Machine::query()
            ->where('public_id', $machineInput['machineId'])
            ->where('is_active', true)
            ->firstOrFail();
        $config = $machine->normalizedDesignFormConfig();
        $required = $config['required'];
        $specificationFields = $config['specificationFields'];
        $specificationRequired = $specificationFields['required'];

        $rules = [
            'ownerId' => ['required', 'string', Rule::exists('users', 'public_id')],
            'title' => [! $config['title'] ? 'exclude' : ($required['title'] ? 'required' : 'nullable'), 'string', 'min:1', 'max:120'],
            'machineId' => ['required', 'string', Rule::exists('machines', 'public_id')->where('is_active', true)],
            'machineTypeId' => [$config['machineType'] && $required['machineType'] ? 'required' : 'nullable', 'string', Rule::exists('machine_types', 'public_id')->where('is_active', true)],
            'categoryId' => [$config['category'] && $required['category'] ? 'required' : 'nullable', 'string', Rule::exists('design_categories', 'public_id')->where('is_active', true)],
            'designFileTypeId' => [$config['designFileType'] && $required['designFileType'] ? 'required' : 'nullable', 'string', Rule::exists('design_file_types', 'public_id')->where('is_active', true)],
            'priceRupees' => [$config['pricing'] && $required['pricing'] ? 'required' : 'nullable', 'numeric', 'min:1', 'max:1000000'],
            'sequenceType' => [$config['sequenceType'] && $required['sequenceType'] ? 'required' : 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'variants' => [$config['designTypes'] && $required['designTypes'] ? 'required' : 'nullable', 'array', 'min:1'],
            'variants.*.typeId' => ['nullable', 'string', 'max:120'],
            'variants.*.customTypeName' => ['nullable', 'string', 'min:1', 'max:120'],
            'variants.*.image' => [$this->adminSpecificationRule($config, $specificationFields, [], 'image', false), 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'variants.*.removeImage' => ['sometimes', 'boolean'],
            'variants.*.nameOrNumber' => [$this->adminSpecificationRule($config, $specificationFields, $specificationRequired, 'nameOrNumber'), 'string', 'min:1', 'max:120'],
            'variants.*.stitchCount' => [$this->adminSpecificationRule($config, $specificationFields, $specificationRequired, 'stitchCount'), 'integer', 'min:0'],
            'variants.*.needle' => [$this->adminSpecificationRule($config, $specificationFields, $specificationRequired, 'needle'), 'integer', 'min:0'],
            'variants.*.area' => [$this->adminSpecificationRule($config, $specificationFields, $specificationRequired, 'area'), 'numeric', 'min:0', 'max:9999999999.99'],
            'variants.*.height' => [$this->adminSpecificationRule($config, $specificationFields, $specificationRequired, 'height'), 'numeric', 'min:0', 'max:9999999999.99'],
            'variants.*.width' => [$this->adminSpecificationRule($config, $specificationFields, $specificationRequired, 'width'), 'numeric', 'min:0', 'max:9999999999.99'],
            'previewImages' => [$creating && $config['previewImages'] && $required['previewImages'] ? 'required_without:previewImage' : 'nullable', 'array', 'min:1', 'max:20'],
            'previewImages.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'previewImage' => [$creating && $config['previewImages'] && $required['previewImages'] ? 'required_without:previewImages' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'stitchFiles' => [$creating && $config['stitchFiles'] && $required['stitchFiles'] ? 'required' : 'nullable', 'array', 'min:1', 'max:20'],
            'stitchFiles.*' => ['required', 'file'],
        ];

        $validated = $request->validate($rules);
        $variantsInput = $config['designTypes'] ? ($validated['variants'] ?? []) : [];
        $pricePaise = $config['pricing'] ? (int) round(((float) ($validated['priceRupees'] ?? 0)) * 100) : 0;

        $catalogTypeIds = collect($variantsInput)
            ->pluck('typeId')
            ->filter()
            ->values();
        $customTypeNames = collect($variantsInput)
            ->pluck('customTypeName')
            ->filter()
            ->map(fn (string $name) => mb_strtolower(trim($name)))
            ->values();

        foreach ($variantsInput as $index => $variant) {
            $hasCatalogType = filled($variant['typeId'] ?? null);
            $hasCustomType = filled($variant['customTypeName'] ?? null);

            if ($hasCatalogType === $hasCustomType) {
                throw ValidationException::withMessages([
                    "variants.{$index}.customTypeName" => 'Choose a catalog design type or enter one custom design type name.',
                ]);
            }
        }

        if ($catalogTypeIds->duplicates()->isNotEmpty() || $customTypeNames->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'variants' => 'Each catalog or custom design type may only be added once.',
            ]);
        }

        if ($config['pricing'] && isset($validated['priceRupees']) && ($pricePaise < 100 || $pricePaise > 100000000)) {
            throw ValidationException::withMessages([
                'priceRupees' => 'Price must be between ₹1 and ₹10,00,000.',
            ]);
        }

        $machineType = null;
        if ($config['machineType'] && filled($validated['machineTypeId'] ?? null)) {
            $machineType = MachineType::query()->where('public_id', $validated['machineTypeId'])->where('is_active', true)->first();
            if ($machineType === null || $machineType->machine_id !== $machine->id) {
                throw ValidationException::withMessages(['machineTypeId' => 'The selected machine type does not belong to this machine.']);
            }
        }

        $category = null;
        if ($config['category'] && filled($validated['categoryId'] ?? null)) {
            $category = DesignCategory::query()->where('public_id', $validated['categoryId'])->where('is_active', true)->first();
            $categoryIsCompatible = $category !== null && ($machineType !== null
                ? $category->machineTypes()->whereKey($machineType->id)->exists()
                : $category->machineTypes()->where('machine_types.machine_id', $machine->id)->where('machine_types.is_active', true)->exists());

            if (! $categoryIsCompatible) {
                throw ValidationException::withMessages(['categoryId' => 'The selected category is not available for this machine.']);
            }
        }

        if ($catalogTypeIds->isNotEmpty() && $category === null) {
            throw ValidationException::withMessages(['categoryId' => 'Select a category before adding design types.']);
        }

        $types = DesignType::query()
            ->whereIn('public_id', $catalogTypeIds)
            ->where('is_active', true)
            ->with('categories:id,public_id,is_active')
            ->get()
            ->keyBy('public_id');

        foreach ($variantsInput as $index => $variant) {
            if (! filled($variant['typeId'] ?? null)) {
                continue;
            }

            $type = $types->get($variant['typeId']);

            $belongsToCategory = $type?->categories->contains(
                fn (DesignCategory $typeCategory) => $typeCategory->id === $category?->id && $typeCategory->is_active,
            );

            if ($type === null || ! $belongsToCategory) {
                throw ValidationException::withMessages([
                    "variants.{$index}.typeId" => 'Every selected design type must belong to the selected category.',
                ]);
            }
        }

        $fileType = $config['designFileType'] && filled($validated['designFileTypeId'] ?? null)
            ? DesignFileType::query()->where('public_id', $validated['designFileTypeId'])->where('is_active', true)->first()
            : null;

        if ($fileType !== null && ! $fileType->machines()->where('machines.id', $machine->id)->exists()) {
            throw ValidationException::withMessages([
                'designFileTypeId' => 'The selected file type is not available for this machine.',
            ]);
        }

        $existingVariants = $existingDesign?->loadMissing('variants.type')->variants
            ->keyBy(fn ($variant) => $this->variantIdentity($variant->type?->public_id, $variant->custom_type_name))
            ?? collect();

        $variants = collect($variantsInput)->map(function (array $variant, int $index) use ($request, $types, $config, $specificationFields, $specificationRequired, $existingVariants) {
            $typeId = $variant['typeId'] ?? null;
            $fallbackName = trim((string) ($types->get($typeId)?->name ?? $variant['customTypeName'] ?? 'Design'));
            $existingVariant = $existingVariants->get($this->variantIdentity($typeId, $variant['customTypeName'] ?? null));
            $imageEnabled = $config['designSpecifications'] && $specificationFields['image'];
            $hasNewImage = $imageEnabled && $request->hasFile("variants.{$index}.image");
            $removeImage = $imageEnabled && $request->boolean("variants.{$index}.removeImage");
            $imagePath = $imageEnabled && ! $removeImage ? $existingVariant?->image_path : null;
            $imageName = $imageEnabled && ! $removeImage ? $existingVariant?->image_name : null;

            if ($imageEnabled && $specificationRequired['image'] && ! $hasNewImage && blank($imagePath)) {
                throw ValidationException::withMessages([
                    "variants.{$index}.image" => 'Upload one image for this design type.',
                ]);
            }

            return [
                'design_type_id' => $typeId ? $types->get($typeId)?->id : null,
                'custom_type_name' => $typeId ? null : trim($variant['customTypeName']),
                'image_path' => $imagePath,
                'image_name' => $imageName,
                '_store_image' => $hasNewImage,
                'name_or_number' => $config['designSpecifications'] && $specificationFields['nameOrNumber']
                    ? trim((string) ($variant['nameOrNumber'] ?? $fallbackName)) : $fallbackName,
                'stitch_count' => $config['designSpecifications'] && $specificationFields['stitchCount'] ? (int) ($variant['stitchCount'] ?? 0) : 0,
                'needle' => $config['designSpecifications'] && $specificationFields['needle'] ? (int) ($variant['needle'] ?? 0) : 0,
                'area' => $config['designSpecifications'] && $specificationFields['area'] ? ($variant['area'] ?? 0) : 0,
                'height' => $config['designSpecifications'] && $specificationFields['height'] ? ($variant['height'] ?? 0) : 0,
                'width' => $config['designSpecifications'] && $specificationFields['width'] ? ($variant['width'] ?? 0) : 0,
                'sort_order' => $index,
            ];
        })->values();
        $explicitTitle = $config['title'] ? trim((string) ($validated['title'] ?? '')) : '';
        $automaticTitle = trim((string) ($variants->first()['name_or_number'] ?? ''));
        $uploadedPreview = collect($request->file('previewImages', []))->first() ?? $request->file('previewImage');

        if ($automaticTitle === '' && $uploadedPreview !== null) {
            $automaticTitle = trim((string) pathinfo($uploadedPreview->getClientOriginalName(), PATHINFO_FILENAME));
        }

        if ($automaticTitle === '' && $existingDesign !== null) {
            $automaticTitle = trim((string) $existingDesign->title);
        }

        $automaticTitle = mb_substr($automaticTitle !== '' ? $automaticTitle : 'Untitled design', 0, 120);
        $resolvedTitle = mb_substr($explicitTitle !== '' ? $explicitTitle : $automaticTitle, 0, 120);
        $firstVariant = $variants->first() ?? ['name_or_number' => $resolvedTitle, 'height' => 0, 'width' => 0];

        return [
            'ownerId' => $validated['ownerId'],
            'designTypeId' => $variants->first()['design_type_id'] ?? null,
            'designFileTypeId' => $fileType?->id,
            'machineDbId' => $machine->id,
            'machineTypeId' => $machineType?->public_id,
            'categoryId' => $category?->public_id,
            'title' => $resolvedTitle,
            'pricePaise' => $pricePaise,
            'sequenceType' => $config['sequenceType'] ? ($validated['sequenceType'] ?? 0) : 0,
            'size' => $firstVariant['height'].' Ã— '.$firstVariant['width'],
            'variants' => $variants->all(),
            'details' => $variants->map(fn (array $variant) => [
                'customTypeName' => $variant['custom_type_name'],
                'names' => [$variant['name_or_number']],
                'stitch' => (string) $variant['stitch_count'],
                'needle' => $variant['needle'],
                'area' => (string) $variant['area'],
                'height' => (string) $variant['height'],
                'width' => (string) $variant['width'],
            ])->all(),
            'previewImagesEnabled' => $config['previewImages'],
            'previewImagesRequired' => $required['previewImages'],
            'stitchFilesEnabled' => $config['stitchFiles'],
            'stitchFilesRequired' => $required['stitchFiles'],
        ];
    }

    private function adminSpecificationRule(array $config, array $fields, array $required, string $key, bool $honourRequired = true): string
    {
        if (! $config['designSpecifications'] || ! $fields[$key]) {
            return 'exclude';
        }

        return $honourRequired && ($required[$key] ?? false) ? 'required' : 'nullable';
    }

    /** @return list<array<string, string|int>> */
    private function variantsForForm(Design $design): array
    {
        if ($design->variants->isNotEmpty()) {
            return $design->variants->map(fn ($variant) => [
                'typeId' => $variant->type?->public_id ?? '',
                'customTypeName' => $variant->custom_type_name ?? '',
                'imageUri' => $variant->image_path ? Storage::disk('public')->url($variant->image_path) : null,
                'imageName' => $variant->image_name,
                'nameOrNumber' => $variant->name_or_number,
                'stitchCount' => $variant->stitch_count,
                'needle' => $variant->needle,
                'area' => $variant->area,
                'height' => $variant->height,
                'width' => $variant->width,
            ])->values()->all();
        }

        return [[
            'typeId' => $design->type?->public_id ?? '',
            'customTypeName' => '',
            'imageUri' => null,
            'imageName' => null,
            'nameOrNumber' => $design->title,
            'stitchCount' => 0,
            'needle' => 0,
            'area' => 0,
            'height' => 0,
            'width' => 0,
        ]];
    }

    private function variantIdentity(?string $typeId, ?string $customTypeName): string
    {
        return filled($typeId)
            ? 'type:'.$typeId
            : 'custom:'.mb_strtolower(trim((string) $customTypeName));
    }

    /** @param list<array<string, mixed>> $variants */
    private function storeVariantImages(Request $request, User $owner, array $variants): array
    {
        return collect($variants)->map(function (array $variant, int $index) use ($request, $owner) {
            if ($variant['_store_image'] ?? false) {
                $image = $request->file("variants.{$index}.image");
                $variant['image_path'] = $image->store('variant-images/'.$owner->public_id, 'public');
                $variant['image_name'] = $image->getClientOriginalName();
            }

            unset($variant['_store_image']);

            return $variant;
        })->values()->all();
    }

    /** @return list<array{name: string, path: string}> */
    private function storeStitchFiles(Request $request, User $owner): array
    {
        return collect($request->file('stitchFiles', []))->map(fn ($file) => [
            'name' => $file->getClientOriginalName(),
            'path' => $file->store('stitch/'.$owner->public_id, 'local'),
        ])->values()->all();
    }

    /** @return list<array{name: string, path: string}> */
    private function storePreviewImages(Request $request, User $owner): array
    {
        $files = collect($request->file('previewImages', []));

        if ($files->isEmpty() && $request->hasFile('previewImage')) {
            $files->push($request->file('previewImage'));
        }

        return $files->map(fn ($file) => [
            'name' => $file->getClientOriginalName(),
            'path' => $file->store('previews/'.$owner->public_id, 'public'),
        ])->values()->all();
    }

    /** @return list<array{name: string, path: string}> */
    private function existingPreviewImages(Design $design): array
    {
        if (is_array($design->images) && $design->images !== []) {
            return collect($design->images)->map(fn (array $image) => array_filter([
                'name' => $image['name'],
                'path' => $image['path'],
                'watermarkedPath' => $image['watermarkedPath'] ?? null,
            ]))->values()->all();
        }

        if ($design->preview_path === null) {
            return [];
        }

        return [[
            'name' => basename($design->preview_path),
            'path' => $design->preview_path,
        ]];
    }

    /** @return list<array{name: string, path: string}> */
    private function existingStitchFiles(Design $design): array
    {
        if (is_array($design->files) && $design->files !== []) {
            return collect($design->files)->map(fn (array $file) => [
                'name' => $file['name'],
                'path' => $file['path'],
            ])->values()->all();
        }

        if ($design->stitch_path === null) {
            return [];
        }

        return [[
            'name' => $design->file_name ?? 'design-file',
            'path' => $design->stitch_path,
        ]];
    }

    /** @return list<array{id: string, name: string, machineTypes: list<array{id: string, name: string, categories: list<array{id: string, name: string, types: list<array{id: string, name: string}>}>}>}> */
    private function catalogForForm(): array
    {
        return Machine::query()
            ->where('is_active', true)
            ->with([
                'machineTypes' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'machineTypes.categories' => fn ($query) => $query
                    ->where('design_categories.is_active', true),
                'machineTypes.categories.activeTypes',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
                'designFormConfig' => $machine->normalizedDesignFormConfig(),
                'machineTypes' => $machine->machineTypes->map(fn (MachineType $machineType) => [
                    'id' => $machineType->public_id,
                    'name' => $machineType->name,
                    'categories' => $machineType->categories->map(fn (DesignCategory $category) => [
                        'id' => $category->public_id,
                        'name' => $category->name,
                        'types' => $category->activeTypes->map(fn ($type) => [
                            'id' => $type->public_id,
                            'name' => $type->name,
                        ])->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{id: string, name: string, extension: string, machineIds: list<string>}> */
    private function fileTypeOptions(?int $includeId = null): array
    {
        return DesignFileType::query()
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->when($includeId !== null, fn ($query) => $query->orWhere('id', $includeId)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with('machines:id,public_id')
            ->get()
            ->map(fn (DesignFileType $fileType) => [
                'id' => $fileType->public_id,
                'name' => $fileType->name,
                'extension' => $fileType->extension,
                'machineIds' => $fileType->machines->pluck('public_id')->values()->all(),
            ])
            ->values()
            ->all();
    }
}
