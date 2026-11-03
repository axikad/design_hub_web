<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\DesignType;
use App\Models\MachineType;
use App\Services\SortOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryAdminController extends Controller
{
    public function __construct(private readonly SortOrderService $sortOrder) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $categories = DesignCategory::query()
            ->withCount(['types', 'machineTypes'])
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DesignCategory $category) => [
                'id' => $category->public_id,
                'name' => $category->name,
                'description' => $category->description,
                'isActive' => $category->is_active,
                'sortOrder' => $category->sort_order,
                'typesCount' => $category->types_count,
                'machineTypesCount' => $category->machine_types_count,
            ]);

        return Inertia::render('admin/categories/index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/categories/form', [
            'category' => null,
            'machineTypes' => $this->machineTypeOptions(),
            'designTypes' => $this->designTypeOptions(),
            'nextSortOrder' => $this->sortOrder->nextPosition(DesignCategory::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        DB::transaction(function () use ($validated): void {
            $position = $validated['attributes']['sort_order'];
            $validated['attributes']['sort_order'] = $position ?? $this->sortOrder->nextPosition(DesignCategory::class);
            $category = DesignCategory::query()->create($validated['attributes']);
            $this->sortOrder->place($category, $position);
            $this->syncMachineTypesPreservingOrder($category, $validated['machineTypeIds']);
            $this->syncDesignTypes($category, $validated['designTypeIds']);
        });

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(string $category): Response
    {
        $model = DesignCategory::query()
            ->with([
                'machineTypes' => fn ($query) => $query->where('machine_types.is_active', true),
                'types' => fn ($query) => $query->where('design_types.is_active', true),
            ])
            ->where('public_id', $category)
            ->firstOrFail();

        return Inertia::render('admin/categories/form', [
            'category' => [
                'id' => $model->public_id,
                'name' => $model->name,
                'description' => $model->description ?? '',
                'isActive' => $model->is_active,
                'sortOrder' => $model->sort_order,
                'machineTypeIds' => $model->machineTypes->pluck('public_id')->values(),
                'designTypeIds' => $model->types->pluck('public_id')->values(),
            ],
            'machineTypes' => $this->machineTypeOptions(),
            'designTypes' => $this->designTypeOptions(),
            'nextSortOrder' => $this->sortOrder->nextPosition(DesignCategory::class),
        ]);
    }

    public function update(Request $request, string $category): RedirectResponse
    {
        $model = DesignCategory::query()->where('public_id', $category)->firstOrFail();
        $validated = $this->validated($request);
        DB::transaction(function () use ($model, $validated): void {
            $position = $validated['attributes']['sort_order'];
            unset($validated['attributes']['sort_order']);
            $model->update($validated['attributes']);
            $this->sortOrder->place($model, $position);
            $this->syncMachineTypesPreservingOrder($model, $validated['machineTypeIds']);
            $this->syncDesignTypes($model, $validated['designTypeIds']);
        });

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(string $category): RedirectResponse
    {
        $model = DesignCategory::query()->where('public_id', $category)->firstOrFail();

        if ($model->types()->exists() || Design::query()->where('category_id', $model->public_id)->exists()) {
            return back()->withErrors(['category' => 'Remove or reassign related design types and designs before deleting this category.']);
        }

        DB::transaction(function () use ($model): void {
            $model->machineTypes()->detach();
            $model->delete();
            $this->sortOrder->remove(DesignCategory::class);
        });

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'distinct', Rule::exists('design_categories', 'public_id')],
            'startPosition' => ['required', 'integer', 'min:1'],
        ]);

        $this->sortOrder->reorderPage(DesignCategory::class, $validated['ids'], $validated['startPosition']);

        return back()->with('success', 'Category order updated.');
    }

    /** @return array{attributes: array<string, mixed>, machineTypeIds: list<int>, designTypeIds: list<int>} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'isActive' => ['boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'machineTypeIds' => ['array'],
            'machineTypeIds.*' => [
                'string',
                'distinct',
                Rule::exists('machine_types', 'public_id')->where('is_active', true),
            ],
            'designTypeIds' => ['array'],
            'designTypeIds.*' => [
                'string',
                'distinct',
                Rule::exists('design_types', 'public_id')->where('is_active', true),
            ],
        ]);

        $designTypes = DesignType::query()
            ->where('is_active', true)
            ->whereIn('public_id', $validated['designTypeIds'] ?? [])
            ->get(['id', 'public_id'])
            ->keyBy('public_id');

        return [
            'attributes' => [
                'name' => trim($validated['name']),
                'description' => isset($validated['description']) ? trim($validated['description']) : null,
                'is_active' => $request->boolean('isActive', true),
                'sort_order' => isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : null,
            ],
            'machineTypeIds' => MachineType::query()
                ->where('is_active', true)
                ->whereIn('public_id', $validated['machineTypeIds'] ?? [])
                ->pluck('id')
                ->all(),
            'designTypeIds' => collect($validated['designTypeIds'] ?? [])
                ->map(fn (string $publicId) => $designTypes->get($publicId)->id)
                ->all(),
        ];
    }

    /** @return list<array{id: string, name: string, machineName: string}> */
    private function machineTypeOptions(): array
    {
        return MachineType::query()
            ->where('is_active', true)
            ->whereHas('machine', fn ($query) => $query->where('is_active', true))
            ->with('machine:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MachineType $machineType) => [
                'id' => $machineType->public_id,
                'name' => $machineType->name,
                'machineName' => $machineType->machine?->name ?? 'Unknown machine',
            ])
            ->values()
            ->all();
    }

    /** @return list<array{id: string, name: string}> */
    private function designTypeOptions(): array
    {
        return DesignType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['public_id', 'name'])
            ->map(fn (DesignType $designType) => [
                'id' => $designType->public_id,
                'name' => $designType->name,
            ])
            ->values()
            ->all();
    }

    /** @param  list<int>  $machineTypeIds */
    private function syncMachineTypesPreservingOrder(DesignCategory $category, array $machineTypeIds): void
    {
        $existingOrders = DB::table('category_machine_type')
            ->where('design_category_id', $category->id)
            ->pluck('sort_order', 'machine_type_id');

        $syncData = collect($machineTypeIds)->mapWithKeys(function (int $machineTypeId) use ($existingOrders): array {
            $position = $existingOrders->get($machineTypeId)
                ?? ((int) DB::table('category_machine_type')->where('machine_type_id', $machineTypeId)->max('sort_order') + 1);

            return [$machineTypeId => ['sort_order' => $position]];
        })->all();

        $category->machineTypes()->sync($syncData);
    }

    /** @param  list<int>  $designTypeIds */
    private function syncDesignTypes(DesignCategory $category, array $designTypeIds): void
    {
        $category->types()->sync(collect($designTypeIds)
            ->values()
            ->mapWithKeys(fn (int $designTypeId, int $index) => [
                $designTypeId => ['sort_order' => $index + 1],
            ])
            ->all());
    }
}
