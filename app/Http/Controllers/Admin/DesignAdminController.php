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
use App\Support\DesignTypeResolver;
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
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $ownerId = (string) $request->string('owner');
        $machineId = (string) $request->string('machine');
        $fileTypeId = (string) $request->string('fileType');

        if ($request->boolean('imageSearch')) {
            $imageSearch = $request->session()->get('admin_design_image_search');

            if (is_array($imageSearch) && is_array($imageSearch['matches'] ?? null)) {
                return Inertia::render('admin/designs/index', $this->designIndexProps(
                    $this->imageSearchPaginator($imageSearch['matches']),
                    [
                        'search' => '',
                        'owner' => '',
                        'machine' => (string) ($imageSearch['machineId'] ?? ''),
                        'fileType' => '',
                    ],
                    true,
                ));
            }
        } else {
            $request->session()->forget('admin_design_image_search');
        }

        $designs = Design::query()
            ->listed()
            ->with(['owner', 'type.category', 'machineType.machine', 'fileType'])
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
            ->when($machineId !== '', fn ($query) => $query->whereHas(
                'machineType.machine',
                fn ($machineQuery) => $machineQuery->where('public_id', $machineId),
            ))
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
    private function designIndexProps(LengthAwarePaginator $designs, array $filters, bool $imageSearchActive = false): array
    {
        return [
            'designs' => $designs,
            'owners' => $this->ownerOptions(),
            'machines' => Machine::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
            ])->values(),
            'fileTypes' => $this->fileTypeOptions(),
            'filters' => $filters,
            'imageSearchActive' => $imageSearchActive,
        ];
    }

    /** @param list<array{designId: string, score: float}> $matches */
    private function imageSearchPaginator(array $matches): LengthAwarePaginator
    {
        $matches = collect($matches);
        $designs = Design::query()
            ->listed()
            ->with(['owner', 'type.category', 'machineType.machine', 'fileType'])
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
            'machineName' => $design->machineType?->machine?->name,
            'machineTypeName' => $design->machineType?->name,
            'thumbnailUri' => $presented['images'][0]['thumbnailUri'] ?? $presented['imageUri'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/designs/form', [
            'design' => null,
            'owners' => $this->ownerOptions(),
            'catalog' => $this->catalogForForm(),
            'fileTypes' => $this->fileTypeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateDesign($request, true);

        $owner = User::query()->where('public_id', $validated['ownerId'])->firstOrFail();

        $previewImages = $this->storePreviewImages($request, $owner);
        $previewPath = $previewImages[0]['path'];
        $stitchFiles = $this->storeStitchFiles($request, $owner);
        $firstStitchFile = $stitchFiles[0];

        $design = DB::transaction(function () use ($owner, $validated, $previewImages, $previewPath, $stitchFiles, $firstStitchFile) {
            $design = Design::query()->create([
                'user_id' => $owner->id,
                'design_type_id' => $validated['designTypeId'],
                'design_file_type_id' => $validated['designFileTypeId'],
                'machine_type_id' => $validated['machineTypeId'],
                'category_id' => $validated['categoryId'],
                'title' => $validated['title'],
                'price_paise' => $validated['pricePaise'],
                'size' => $validated['size'],
                'color' => '',
                'details' => $validated['details'],
                'images' => $previewImages,
                'files' => $stitchFiles,
                'preview_path' => $previewPath,
                'stitch_path' => $firstStitchFile['path'],
                'file_name' => $firstStitchFile['name'],
            ]);

            $design->variants()->createMany($validated['variants']);

            return $design;
        });

        $this->imageMatching->syncDesign($design);

        return redirect()->route('admin.designs.index')->with('success', 'Design created.');
    }

    public function edit(string $design): Response
    {
        $model = Design::query()
            ->listed()
            ->with(['owner', 'type.category', 'fileType', 'machineType.machine', 'variants.type'])
            ->where('public_id', $design)
            ->firstOrFail();

        $presented = $this->presenter->forUser($model, $model->owner);

        return Inertia::render('admin/designs/form', [
            'design' => [
                'id' => $model->public_id,
                'ownerId' => $model->owner?->public_id,
                'categoryId' => $model->category_id ?? $model->type?->category?->public_id,
                'machineTypeId' => $model->machine_type_id,
                'machineId' => $model->machineType?->machine?->public_id,
                'designFileTypeId' => $model->fileType?->public_id,
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
            'owners' => $this->ownerOptions(),
            'catalog' => $this->catalogForForm(),
            'fileTypes' => $this->fileTypeOptions($model->design_file_type_id),
        ]);
    }

    public function update(Request $request, string $design): RedirectResponse
    {
        $model = Design::query()->listed()->where('public_id', $design)->firstOrFail();
        $validated = $this->validateDesign($request, false);

        $owner = User::query()->where('public_id', $validated['ownerId'])->firstOrFail();

        $previewImages = $this->existingPreviewImages($model);
        if ($request->hasFile('previewImages') || $request->hasFile('previewImage')) {
            Storage::disk('public')->delete(collect($previewImages)->pluck('path')->filter()->unique()->all());
            $previewImages = $this->storePreviewImages($request, $owner);
        }

        $previewPath = $previewImages[0]['path'] ?? null;

        $stitchFiles = $this->existingStitchFiles($model);
        if ($request->hasFile('stitchFiles')) {
            Storage::disk('local')->delete(collect($stitchFiles)->pluck('path')->filter()->unique()->all());
            $stitchFiles = $this->storeStitchFiles($request, $owner);
        }

        if ($stitchFiles === []) {
            throw ValidationException::withMessages([
                'stitchFiles' => 'Upload at least one stitch file.',
            ]);
        }

        $firstStitchFile = $stitchFiles[0];

        DB::transaction(function () use ($model, $owner, $validated, $previewImages, $previewPath, $stitchFiles, $firstStitchFile) {
            $model->update([
                'user_id' => $owner->id,
                'design_type_id' => $validated['designTypeId'],
                'design_file_type_id' => $validated['designFileTypeId'],
                'machine_type_id' => $validated['machineTypeId'],
                'category_id' => $validated['categoryId'],
                'title' => $validated['title'],
                'price_paise' => $validated['pricePaise'],
                'size' => $validated['size'],
                'color' => '',
                'details' => $validated['details'],
                'images' => $previewImages,
                'files' => $stitchFiles,
                'preview_path' => $previewPath,
                'stitch_path' => $firstStitchFile['path'],
                'file_name' => $firstStitchFile['name'],
            ]);

            $model->variants()->delete();
            $model->variants()->createMany($validated['variants']);
        });

        $this->imageMatching->syncDesign($model->fresh());

        return redirect()->route('admin.designs.index')->with('success', 'Design updated.');
    }

    public function destroy(string $design): RedirectResponse
    {
        $model = Design::query()->listed()->where('public_id', $design)->firstOrFail();
        $model->update(['deleted_at' => now()]);
        $this->imageMatching->removeDesign($model);

        return redirect()->route('admin.designs.index')->with('success', 'Design removed from marketplace.');
    }

    /** @return list<array{id: string, label: string}> */
    private function ownerOptions(): array
    {
        return User::query()
            ->whereNotNull('phone_key')
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
    private function validateDesign(Request $request, bool $creating): array
    {
        $rules = [
            'ownerId' => ['required', 'string', Rule::exists('users', 'public_id')],
            'machineId' => ['required', 'string', Rule::exists('machines', 'public_id')->where('is_active', true)],
            'machineTypeId' => ['required', 'string', Rule::exists('machine_types', 'public_id')->where('is_active', true)],
            'categoryId' => ['required', 'string', Rule::exists('design_categories', 'public_id')->where('is_active', true)],
            'designFileTypeId' => ['required', 'string', Rule::exists('design_file_types', 'public_id')->where('is_active', true)],
            'priceRupees' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.typeId' => ['nullable', 'string', 'max:120'],
            'variants.*.customTypeName' => ['nullable', 'string', 'min:1', 'max:120'],
            'variants.*.nameOrNumber' => ['required', 'string', 'min:1', 'max:120'],
            'variants.*.stitchCount' => ['required', 'integer', 'min:0'],
            'variants.*.needle' => ['required', 'integer', 'min:0'],
            'variants.*.sequenceType' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'variants.*.area' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'variants.*.height' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'variants.*.width' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'previewImages' => [$creating ? 'required_without:previewImage' : 'nullable', 'array', 'min:1', 'max:20'],
            'previewImages.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'previewImage' => [$creating ? 'required_without:previewImages' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'stitchFiles' => [$creating ? 'required' : 'nullable', 'array', 'min:1', 'max:20'],
            'stitchFiles.*' => ['required', 'file', 'max:5120'],
        ];

        $validated = $request->validate($rules);
        $pricePaise = (int) round(((float) $validated['priceRupees']) * 100);

        $catalogTypeIds = collect($validated['variants'])
            ->pluck('typeId')
            ->filter()
            ->values();
        $customTypeNames = collect($validated['variants'])
            ->pluck('customTypeName')
            ->filter()
            ->map(fn (string $name) => mb_strtolower(trim($name)))
            ->values();

        foreach ($validated['variants'] as $index => $variant) {
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

        if ($pricePaise < 100 || $pricePaise > 100000000) {
            throw ValidationException::withMessages([
                'priceRupees' => 'Price must be between ₹1 and ₹10,00,000.',
            ]);
        }

        try {
            $selection = $catalogTypeIds->isNotEmpty()
                ? DesignTypeResolver::resolveMobileSelection(
                    $catalogTypeIds->first(),
                    $validated['categoryId'],
                    $validated['machineTypeId'],
                )
                : DesignTypeResolver::resolveCategorySelection(
                    $validated['categoryId'],
                    $validated['machineTypeId'],
                );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'categoryId' => $exception->getMessage(),
            ]);
        }

        $types = DesignType::query()
            ->whereIn('public_id', $catalogTypeIds)
            ->where('is_active', true)
            ->with('categories:id,public_id,is_active')
            ->get()
            ->keyBy('public_id');

        foreach ($validated['variants'] as $index => $variant) {
            if (! filled($variant['typeId'] ?? null)) {
                continue;
            }

            $type = $types->get($variant['typeId']);

            $belongsToCategory = $type?->categories->contains(
                fn (DesignCategory $category) => $category->public_id === $validated['categoryId'] && $category->is_active,
            );

            if ($type === null || ! $belongsToCategory) {
                throw ValidationException::withMessages([
                    "variants.{$index}.typeId" => 'Every selected design type must belong to the selected category.',
                ]);
            }
        }

        $machineType = MachineType::query()
            ->with('machine')
            ->where('public_id', $selection['machine_type_id'])
            ->firstOrFail();

        if ($machineType->machine?->public_id !== $validated['machineId']) {
            throw ValidationException::withMessages([
                'machineTypeId' => 'The selected machine type does not belong to this machine.',
            ]);
        }

        $fileType = DesignFileType::query()
            ->where('public_id', $validated['designFileTypeId'])
            ->where('is_active', true)
            ->firstOrFail();

        if (! $fileType->machines()->where('machines.id', $machineType->machine_id)->exists()) {
            throw ValidationException::withMessages([
                'designFileTypeId' => 'The selected file type is not available for this machine.',
            ]);
        }

        $variants = collect($validated['variants'])->map(function (array $variant, int $index) use ($types) {
            $typeId = $variant['typeId'] ?? null;

            return [
                'design_type_id' => $typeId ? $types->get($typeId)?->id : null,
                'custom_type_name' => $typeId ? null : trim($variant['customTypeName']),
                'name_or_number' => trim($variant['nameOrNumber']),
                'stitch_count' => (int) $variant['stitchCount'],
                'needle' => (int) $variant['needle'],
                'sequence_type' => $variant['sequenceType'] ?? 0,
                'area' => $variant['area'],
                'height' => $variant['height'],
                'width' => $variant['width'],
                'sort_order' => $index,
            ];
        })->values();
        $firstVariant = $variants->first();

        return [
            'ownerId' => $validated['ownerId'],
            'designTypeId' => $selection['design_type_id'],
            'designFileTypeId' => $fileType->id,
            'machineTypeId' => $selection['machine_type_id'],
            'categoryId' => $selection['category_id'],
            'title' => $firstVariant['name_or_number'],
            'pricePaise' => $pricePaise,
            'size' => $firstVariant['height'].' Ã— '.$firstVariant['width'],
            'variants' => $variants->all(),
            'details' => $variants->map(fn (array $variant) => [
                'customTypeName' => $variant['custom_type_name'],
                'names' => [$variant['name_or_number']],
                'stitch' => (string) $variant['stitch_count'],
                'needle' => $variant['needle'],
                'sequenceType' => (string) $variant['sequence_type'],
                'area' => (string) $variant['area'],
                'height' => (string) $variant['height'],
                'width' => (string) $variant['width'],
            ])->all(),
        ];
    }

    /** @return list<array<string, string|int>> */
    private function variantsForForm(Design $design): array
    {
        if ($design->variants->isNotEmpty()) {
            return $design->variants->map(fn ($variant) => [
                'typeId' => $variant->type?->public_id ?? '',
                'customTypeName' => $variant->custom_type_name ?? '',
                'nameOrNumber' => $variant->name_or_number,
                'stitchCount' => $variant->stitch_count,
                'needle' => $variant->needle,
                'sequenceType' => $variant->sequence_type,
                'area' => $variant->area,
                'height' => $variant->height,
                'width' => $variant->width,
            ])->values()->all();
        }

        return [[
            'typeId' => $design->type?->public_id ?? '',
            'customTypeName' => '',
            'nameOrNumber' => $design->title,
            'stitchCount' => 0,
            'needle' => 0,
            'sequenceType' => 0,
            'area' => 0,
            'height' => 0,
            'width' => 0,
        ]];
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
            return collect($design->images)->map(fn (array $image) => [
                'name' => $image['name'],
                'path' => $image['path'],
            ])->values()->all();
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
                    ->where('design_categories.is_active', true)
                    ->orderBy('design_categories.sort_order')
                    ->orderBy('design_categories.name'),
                'machineTypes.categories.activeTypes' => fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
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
