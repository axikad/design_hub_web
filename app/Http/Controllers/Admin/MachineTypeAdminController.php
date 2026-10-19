<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\Machine;
use App\Models\MachineType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MachineTypeAdminController extends Controller
{
    public function index(): Response
    {
        $machineTypes = MachineType::query()
            ->with(['machine', 'categories'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (MachineType $machineType) => [
                'id' => $machineType->public_id,
                'name' => $machineType->name,
                'machineName' => $machineType->machine?->name,
                'categoryNames' => $machineType->categories->pluck('name')->values(),
                'isActive' => $machineType->is_active,
                'sortOrder' => $machineType->sort_order,
            ]);

        return Inertia::render('admin/machine-types/index', ['machineTypes' => $machineTypes]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/machine-types/form', [
            'machineType' => null,
            ...$this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $machineType = MachineType::query()->create($validated['attributes']);
        $machineType->categories()->sync($validated['categoryIds']);

        return redirect()->route('admin.machine-types.index')->with('success', 'Machine type created.');
    }

    public function edit(string $machineType): Response
    {
        $model = MachineType::query()->with(['machine', 'categories'])->where('public_id', $machineType)->firstOrFail();

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
        ]);
    }

    public function update(Request $request, string $machineType): RedirectResponse
    {
        $model = MachineType::query()->where('public_id', $machineType)->firstOrFail();
        $validated = $this->validated($request);
        $model->update($validated['attributes']);
        $model->categories()->sync($validated['categoryIds']);

        return redirect()->route('admin.machine-types.index')->with('success', 'Machine type updated.');
    }

    public function destroy(string $machineType): RedirectResponse
    {
        $model = MachineType::query()->where('public_id', $machineType)->firstOrFail();

        if (Design::query()->where('machine_type_id', $model->public_id)->exists()) {
            return back()->withErrors(['machineType' => 'Reassign designs before deleting this machine type.']);
        }

        $model->delete();

        return redirect()->route('admin.machine-types.index')->with('success', 'Machine type deleted.');
    }

    private function options(): array
    {
        return [
            'machines' => Machine::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
            ])->values(),
            'categories' => DesignCategory::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (DesignCategory $category) => [
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
            'categoryIds.*' => ['string', Rule::exists('design_categories', 'public_id')],
            'isActive' => ['boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $machine = Machine::query()->where('public_id', $validated['machineId'])->firstOrFail();
        $categoryIds = DesignCategory::query()->whereIn('public_id', $validated['categoryIds'] ?? [])->pluck('id')->all();

        return [
            'attributes' => [
                'machine_id' => $machine->id,
                'name' => trim($validated['name']),
                'is_active' => $request->boolean('isActive', true),
                'sort_order' => (int) ($validated['sortOrder'] ?? 0),
            ],
            'categoryIds' => $categoryIds,
        ];
    }
}
