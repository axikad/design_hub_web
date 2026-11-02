<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\Machine;
use App\Models\MachineType;
use App\Services\SortOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MachineTypeAdminController extends Controller
{
    public function __construct(private readonly SortOrderService $sortOrder) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $machineId = (string) $request->string('machine');

        $machineTypes = MachineType::query()
            ->with([
                'machine',
                'categories' => fn ($query) => $query
                    ->where('design_categories.is_active', true),
            ])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->when($machineId !== '', fn ($query) => $query->whereHas(
                'machine',
                fn ($machineQuery) => $machineQuery->where('public_id', $machineId),
            ))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MachineType $machineType) => [
                'id' => $machineType->public_id,
                'name' => $machineType->name,
                'machineName' => $machineType->machine?->name,
                'categoryNames' => $machineType->categories->pluck('name')->values(),
                'isActive' => $machineType->is_active,
                'sortOrder' => $machineType->sort_order,
            ]);

        return Inertia::render('admin/machine-types/index', [
            'machineTypes' => $machineTypes,
            'machines' => $this->options()['machines'],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'machine' => $machineId,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/machine-types/form', [
            'machineType' => null,
            ...$this->options(),
            'nextSortOrders' => $this->nextSortOrders(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $position = $validated['attributes']['sort_order'];
        $scope = ['machine_id' => $validated['attributes']['machine_id']];
        $validated['attributes']['sort_order'] = $position ?? $this->sortOrder->nextPosition(MachineType::class, $scope);
        $machineType = MachineType::query()->create($validated['attributes']);
        $this->sortOrder->place($machineType, $position, $scope, $scope);
        $machineType->categories()->sync($this->categorySyncData($validated['categoryIds']));

        return redirect()->route('admin.machine-types.index')->with('success', 'Machine type created.');
    }

    public function edit(string $machineType): Response
    {
        $model = MachineType::query()
            ->with([
                'machine',
                'categories' => fn ($query) => $query
                    ->where('design_categories.is_active', true),
            ])
            ->where('public_id', $machineType)
            ->firstOrFail();

        return Inertia::render('admin/machine-types/form', [
            'machineType' => [
                'id' => $model->public_id,
                'machineId' => $model->machine?->public_id,
                'name' => $model->name,
                'categoryIds' => $model->categories->pluck('public_id')->values(),
                'isActive' => $model->is_active,
                'sortOrder' => $model->sort_order,
            ],
            ...$this->options(),
            'nextSortOrders' => $this->nextSortOrders(),
        ]);
    }

    public function update(Request $request, string $machineType): RedirectResponse
    {
        $model = MachineType::query()->where('public_id', $machineType)->firstOrFail();
        $validated = $this->validated($request);
        $oldScope = ['machine_id' => $model->machine_id];
        $newScope = ['machine_id' => $validated['attributes']['machine_id']];
        $position = $validated['attributes']['sort_order'];
        unset($validated['attributes']['sort_order']);
        $model->update($validated['attributes']);
        $this->sortOrder->place($model, $position, $oldScope, $newScope);
        $model->categories()->sync($this->categorySyncData($validated['categoryIds']));

        return redirect()->route('admin.machine-types.index')->with('success', 'Machine type updated.');
    }

    public function destroy(string $machineType): RedirectResponse
    {
        $model = MachineType::query()->where('public_id', $machineType)->firstOrFail();

        if (Design::query()->where('machine_type_id', $model->public_id)->exists()) {
            return back()->withErrors(['machineType' => 'Reassign designs before deleting this machine type.']);
        }

        $scope = ['machine_id' => $model->machine_id];
        $model->delete();
        $this->sortOrder->remove(MachineType::class, $scope);

        return redirect()->route('admin.machine-types.index')->with('success', 'Machine type deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'machineId' => ['required', 'string', Rule::exists('machines', 'public_id')],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'distinct', Rule::exists('machine_types', 'public_id')],
            'startPosition' => ['required', 'integer', 'min:1'],
        ]);
        $machine = Machine::query()->where('public_id', $validated['machineId'])->firstOrFail();
        $this->sortOrder->reorderPage(
            MachineType::class,
            $validated['ids'],
            $validated['startPosition'],
            ['machine_id' => $machine->id],
        );

        return back()->with('success', 'Machine type order updated.');
    }

    private function options(): array
    {
        return [
            'machines' => Machine::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
            ])->values(),
            'categories' => DesignCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()->map(fn (DesignCategory $category) => [
                'id' => $category->public_id,
                'name' => $category->name,
            ])->values(),
        ];
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'machineId' => ['required', 'string', Rule::exists('machines', 'public_id')],
            'name' => ['required', 'string', 'max:80'],
            'categoryIds' => ['array'],
            'categoryIds.*' => [
                'string',
                Rule::exists('design_categories', 'public_id')->where('is_active', true),
            ],
            'isActive' => ['boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $machine = Machine::query()->where('public_id', $validated['machineId'])->firstOrFail();
        $categories = DesignCategory::query()
            ->where('is_active', true)
            ->whereIn('public_id', $validated['categoryIds'] ?? [])
            ->get(['id', 'public_id'])
            ->keyBy('public_id');
        $categoryIds = collect($validated['categoryIds'] ?? [])
            ->map(fn (string $publicId) => $categories->get($publicId)?->id)
            ->filter()
            ->values()
            ->all();

        return [
            'attributes' => [
                'machine_id' => $machine->id,
                'name' => trim($validated['name']),
                'is_active' => $request->boolean('isActive', true),
                'sort_order' => isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : null,
            ],
            'categoryIds' => $categoryIds,
        ];
    }

    /** @param  list<int>  $categoryIds */
    private function categorySyncData(array $categoryIds): array
    {
        return collect($categoryIds)->mapWithKeys(fn (int $categoryId, int $index) => [
            $categoryId => ['sort_order' => $index + 1],
        ])->all();
    }

    /** @return array<string, int> */
    private function nextSortOrders(): array
    {
        return Machine::query()->get(['id', 'public_id'])->mapWithKeys(fn (Machine $machine) => [
            $machine->public_id => $this->sortOrder->nextPosition(MachineType::class, ['machine_id' => $machine->id]),
        ])->all();
    }
}
