<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\DesignFileType;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\User;
use App\Services\DesignPresenter;
use App\Support\DesignCatalog;
use App\Support\DesignTypeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DesignAdminController extends Controller
{
    public function __construct(private readonly DesignPresenter $presenter) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $ownerId = (string) $request->string('owner');
        $machineId = (string) $request->string('machine');
        $fileTypeId = (string) $request->string('fileType');

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
            ->through(function (Design $design) {
                $presented = $this->presenter->forUser($design, $design->owner);

                return array_merge($presented, [
                    'ownerName' => $design->owner?->name,
                    'machineName' => $design->machineType?->machine?->name,
                    'machineTypeName' => $design->machineType?->name,
                    'thumbnailUri' => $presented['images'][0]['thumbnailUri'] ?? $presented['imageUri'],
                ]);
            });

        return Inertia::render('admin/designs/index', [
            'designs' => $designs,
            'owners' => $this->ownerOptions(),
            'machines' => Machine::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
            ])->values(),
            'fileTypes' => $this->fileTypeOptions(),
            'filters' => [
                'search' => $search,
                'owner' => $ownerId,
                'machine' => $machineId,
                'fileType' => $fileTypeId,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/designs/form', [
            'design' => null,
            'owners' => $this->ownerOptions(),
            'sizeOptions' => DesignCatalog::sizes(),
            'colorOptions' => DesignCatalog::colors(),
            'catalog' => $this->catalogForForm(),
            'fileTypes' => $this->fileTypeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateDesign($request, true);

        $owner = User::query()->where('public_id', $validated['ownerId'])->firstOrFail();

        $previewPath = $request->file('previewImage')?->store('previews/'.$owner->public_id, 'public');
        $stitchPath = $request->file('stitchFile')?->store('stitch/'.$owner->public_id, 'local');

        Design::query()->create([
            'user_id' => $owner->id,
            'design_type_id' => $validated['designTypeId'],
            'design_file_type_id' => $validated['designFileTypeId'],
            'machine_type_id' => $validated['machineTypeId'],
            'category_id' => $validated['categoryId'],
            'title' => $validated['title'],
            'price_paise' => $validated['pricePaise'],
            'size' => $validated['size'],
            'color' => $validated['color'],
            'preview_path' => $previewPath,
            'stitch_path' => $stitchPath,
            'file_name' => $request->file('stitchFile')?->getClientOriginalName(),
        ]);

        return redirect()->route('admin.designs.index')->with('success', 'Design created.');
    }

    public function edit(string $design): Response
    {
        $model = Design::query()
            ->listed()
            ->with(['owner', 'type.category', 'fileType', 'machineType.machine'])
            ->where('public_id', $design)
            ->firstOrFail();

        return Inertia::render('admin/designs/form', [
            'design' => [
                'id' => $model->public_id,
                'ownerId' => $model->owner?->public_id,
                'typeId' => $model->type?->public_id,
                'categoryId' => $model->category_id ?? $model->type?->category?->public_id,
                'machineTypeId' => $model->machine_type_id,
                'machineId' => $model->machineType?->machine?->public_id,
                'designFileTypeId' => $model->fileType?->public_id,
                'title' => $model->title,
                'priceRupees' => $model->price_paise !== null ? number_format($model->price_paise / 100, 2, '.', '') : '',
                'size' => $model->size,
                'color' => $model->color,
                'fileName' => $model->file_name,
                'imageUri' => $this->presenter->previewUrl($model),
            ],
            'owners' => $this->ownerOptions(),
            'sizeOptions' => DesignCatalog::sizes(),
            'colorOptions' => DesignCatalog::colors(),
            'catalog' => $this->catalogForForm(),
            'fileTypes' => $this->fileTypeOptions($model->design_file_type_id),
        ]);
    }

    public function update(Request $request, string $design): RedirectResponse
    {
        $model = Design::query()->listed()->where('public_id', $design)->firstOrFail();
        $validated = $this->validateDesign($request, false);

        $owner = User::query()->where('public_id', $validated['ownerId'])->firstOrFail();

        $previewPath = $model->preview_path;
        if ($request->hasFile('previewImage')) {
            if ($previewPath) {
                Storage::disk('public')->delete($previewPath);
            }
            $previewPath = $request->file('previewImage')->store('previews/'.$owner->public_id, 'public');
        }

        $stitchPath = $model->stitch_path;
        $fileName = $model->file_name;
        if ($request->hasFile('stitchFile')) {
            if ($stitchPath) {
                Storage::disk('local')->delete($stitchPath);
            }
            $stitchPath = $request->file('stitchFile')->store('stitch/'.$owner->public_id, 'local');
            $fileName = $request->file('stitchFile')->getClientOriginalName();
        }

        $model->update([
            'user_id' => $owner->id,
            'design_type_id' => $validated['designTypeId'],
            'design_file_type_id' => $validated['designFileTypeId'],
            'machine_type_id' => $validated['machineTypeId'],
            'category_id' => $validated['categoryId'],
            'title' => $validated['title'],
            'price_paise' => $validated['pricePaise'],
            'size' => $validated['size'],
            'color' => $validated['color'],
            'preview_path' => $previewPath,
            'stitch_path' => $stitchPath,
            'file_name' => $fileName,
        ]);

        return redirect()->route('admin.designs.index')->with('success', 'Design updated.');
    }

    public function destroy(string $design): RedirectResponse
    {
        $model = Design::query()->listed()->where('public_id', $design)->firstOrFail();
        $model->update(['deleted_at' => now()]);

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

    /** @return array{ownerId: string, designTypeId: int, designFileTypeId: int, machineTypeId: string, categoryId: string, title: string, pricePaise: int, size: string, color: string} */
    private function validateDesign(Request $request, bool $creating): array
    {
        $rules = [
            'ownerId' => ['required', 'string', Rule::exists('users', 'public_id')],
            'machineId' => ['required', 'string', Rule::exists('machines', 'public_id')->where('is_active', true)],
            'machineTypeId' => ['required', 'string', Rule::exists('machine_types', 'public_id')->where('is_active', true)],
            'categoryId' => ['required', 'string', Rule::exists('design_categories', 'public_id')->where('is_active', true)],
            'typeId' => ['required', 'string', Rule::exists('design_types', 'public_id')],
            'designFileTypeId' => ['required', 'string', Rule::exists('design_file_types', 'public_id')->where('is_active', true)],
            'title' => ['required', 'string', 'min:1', 'max:40'],
            'priceRupees' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'size' => ['required', 'string', 'max:120'],
            'color' => ['required', 'string', 'max:120'],
            'previewImage' => [$creating ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'stitchFile' => [$creating ? 'required' : 'nullable', 'file', 'max:5120'],
        ];

        $validated = $request->validate($rules);
        $pricePaise = (int) round(((float) $validated['priceRupees']) * 100);

        if ($pricePaise < 100 || $pricePaise > 100000000) {
            throw ValidationException::withMessages([
                'priceRupees' => 'Price must be between ₹1 and ₹10,00,000.',
            ]);
        }

        try {
            $selection = DesignTypeResolver::resolveMobileSelection(
                $validated['typeId'],
                $validated['categoryId'],
                $validated['machineTypeId'],
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'typeId' => $exception->getMessage(),
            ]);
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

        $fileTypeId = DesignFileType::query()
            ->where('public_id', $validated['designFileTypeId'])
            ->where('is_active', true)
            ->value('id');

        return [
            'ownerId' => $validated['ownerId'],
            'designTypeId' => $selection['design_type_id'],
            'designFileTypeId' => $fileTypeId,
            'machineTypeId' => $selection['machine_type_id'],
            'categoryId' => $selection['category_id'],
            'title' => trim($validated['title']),
            'pricePaise' => $pricePaise,
            'size' => trim($validated['size']),
            'color' => trim($validated['color']),
        ];
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

    /** @return list<array{id: string, name: string, extension: string}> */
    private function fileTypeOptions(?int $includeId = null): array
    {
        return DesignFileType::query()
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->when($includeId !== null, fn ($query) => $query->orWhereKey($includeId)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (DesignFileType $fileType) => [
                'id' => $fileType->public_id,
                'name' => $fileType->name,
                'extension' => $fileType->extension,
            ])
            ->values()
            ->all();
    }
}
