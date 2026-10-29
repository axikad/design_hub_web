<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Services\SortOrderService;
use App\Support\MachineDesignFormConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MachineAdminController extends Controller
{
    public function __construct(private readonly SortOrderService $sortOrder) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $machines = Machine::query()
            ->withCount('machineTypes')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
                'imageUri' => $machine->imageUrl(),
                'isActive' => $machine->is_active,
                'sortOrder' => $machine->sort_order,
                'machineTypesCount' => $machine->machine_types_count,
            ]);

        return Inertia::render('admin/machines/index', [
            'machines' => $machines,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/machines/form', [
            'machine' => null,
            'nextSortOrder' => $this->sortOrder->nextPosition(Machine::class),
            'defaultDesignFormConfig' => MachineDesignFormConfig::defaults(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validated($request);
        $position = $attributes['sort_order'];
        $attributes['sort_order'] = $position ?? $this->sortOrder->nextPosition(Machine::class);
        $machine = Machine::query()->create($attributes);
        $this->sortOrder->place($machine, $position);
        $this->syncImage($request, $machine);

        return redirect()->route('admin.machines.index')->with('success', 'Machine created.');
    }

    public function edit(string $machine): Response
    {
        $model = Machine::query()->where('public_id', $machine)->firstOrFail();

        return Inertia::render('admin/machines/form', [
            'machine' => [
                'id' => $model->public_id,
                'name' => $model->name,
                'imageUri' => $model->imageUrl(),
                'isActive' => $model->is_active,
                'sortOrder' => $model->sort_order,
                'designFormConfig' => $model->normalizedDesignFormConfig(),
            ],
            'nextSortOrder' => $this->sortOrder->nextPosition(Machine::class),
            'defaultDesignFormConfig' => MachineDesignFormConfig::defaults(),
        ]);
    }

    public function update(Request $request, string $machine): RedirectResponse
    {
        $model = Machine::query()->where('public_id', $machine)->firstOrFail();
        $attributes = $this->validated($request);
        $position = $attributes['sort_order'];
        unset($attributes['sort_order']);
        $model->update($attributes);
        $this->sortOrder->place($model, $position);
        $this->syncImage($request, $model);

        return redirect()->route('admin.machines.index')->with('success', 'Machine updated.');
    }

    public function destroy(string $machine): RedirectResponse
    {
        $model = Machine::query()->where('public_id', $machine)->firstOrFail();

        if ($model->machineTypes()->exists()) {
            return back()->withErrors(['machine' => 'Delete or reassign this machine\'s types first.']);
        }

        $imagePath = $model->image_path;
        $model->delete();
        $this->sortOrder->remove(Machine::class);

        if ($imagePath !== null) {
            Storage::disk('public')->delete($imagePath);
        }

        return redirect()->route('admin.machines.index')->with('success', 'Machine deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'distinct', 'exists:machines,public_id'],
            'startPosition' => ['required', 'integer', 'min:1'],
        ]);

        $this->sortOrder->reorderPage(Machine::class, $validated['ids'], $validated['startPosition']);

        return back()->with('success', 'Machine order updated.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'isActive' => ['boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'removeImage' => ['nullable', 'boolean'],
            'designFormConfig' => ['sometimes', 'array'],
            'designFormConfig.machineType' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.category' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.designTypes' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.designSpecifications' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.sequenceType' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.designFileType' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.previewImages' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.stitchFiles' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.pricing' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.required' => ['sometimes', 'array'],
            'designFormConfig.required.machineType' => ['sometimes', 'boolean'],
            'designFormConfig.required.category' => ['sometimes', 'boolean'],
            'designFormConfig.required.designTypes' => ['sometimes', 'boolean'],
            'designFormConfig.required.designSpecifications' => ['sometimes', 'boolean'],
            'designFormConfig.required.sequenceType' => ['sometimes', 'boolean'],
            'designFormConfig.required.designFileType' => ['sometimes', 'boolean'],
            'designFormConfig.required.previewImages' => ['sometimes', 'boolean'],
            'designFormConfig.required.stitchFiles' => ['sometimes', 'boolean'],
            'designFormConfig.required.pricing' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields' => ['sometimes', 'array'],
            'designFormConfig.specificationFields.nameOrNumber' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.stitchCount' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.needle' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.area' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.height' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.width' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required' => ['sometimes', 'array'],
            'designFormConfig.specificationFields.required.nameOrNumber' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required.stitchCount' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required.needle' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required.area' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required.height' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required.width' => ['sometimes', 'boolean'],
        ]);

        return [
            'name' => trim($validated['name']),
            'is_active' => $request->boolean('isActive', true),
            'sort_order' => isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : null,
            'design_form_config' => MachineDesignFormConfig::normalize($validated['designFormConfig'] ?? null),
        ];
    }

    private function syncImage(Request $request, Machine $machine): void
    {
        if ($request->hasFile('image')) {
            $oldPath = $machine->image_path;
            $path = $request->file('image')->store('machines/'.$machine->public_id, 'public');

            $machine->update(['image_path' => $path]);

            if ($oldPath !== null && $oldPath !== $path) {
                Storage::disk('public')->delete($oldPath);
            }

            return;
        }

        if ($request->boolean('removeImage') && $machine->image_path !== null) {
            $oldPath = $machine->image_path;
            $machine->update(['image_path' => null]);
            Storage::disk('public')->delete($oldPath);
        }
    }
}
