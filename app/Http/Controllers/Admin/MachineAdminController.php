<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Services\DesignWatermarkService;
use App\Services\SortOrderService;
use App\Support\MachineDesignFormConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MachineAdminController extends Controller
{
    public function __construct(
        private readonly SortOrderService $sortOrder,
        private readonly DesignWatermarkService $watermarks,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $machines = Machine::query()
            ->withCount(['machineTypes', 'designs', 'users'])
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
                'designsCount' => $machine->designs_count,
                'usersCount' => $machine->users_count,
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
            'defaultWatermarkConfig' => Machine::defaultWatermarkConfig(),
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
                'watermarkConfig' => $model->normalizedWatermarkConfig(),
            ],
            'nextSortOrder' => $this->sortOrder->nextPosition(Machine::class),
            'defaultDesignFormConfig' => MachineDesignFormConfig::defaults(),
            'defaultWatermarkConfig' => Machine::defaultWatermarkConfig(),
        ]);
    }

    public function update(Request $request, string $machine): RedirectResponse
    {
        $model = Machine::query()->where('public_id', $machine)->firstOrFail();
        $previousWatermarkConfig = $model->normalizedWatermarkConfig();
        $attributes = $this->validated($request);
        $position = $attributes['sort_order'];
        unset($attributes['sort_order']);
        $model->update($attributes);
        $this->sortOrder->place($model, $position);
        $this->syncImage($request, $model);
        $model->refresh();

        if ($previousWatermarkConfig !== $model->normalizedWatermarkConfig() || $model->normalizedWatermarkConfig()['enabled']) {
            $this->watermarks->syncMachine($model);
        }

        return redirect()->route('admin.machines.index')->with('success', 'Machine updated.');
    }

    public function destroy(string $machine): RedirectResponse
    {
        $model = Machine::query()->where('public_id', $machine)->firstOrFail();

        $imagePath = $model->image_path;
        $machineTypesCount = $model->machineTypes()->count();
        $designsCount = $model->designs()->count();
        $usersCount = $model->users()->count();

        DB::transaction(function () use ($model): void {
            $model->machineTypes()->update(['machine_id' => null]);
            $model->designs()->update(['machine_id' => null]);
            $model->users()->detach();
            $model->designFileTypes()->detach();
            $model->delete();
            $this->sortOrder->remove(Machine::class);
        });

        if ($imagePath !== null) {
            Storage::disk('public')->delete($imagePath);
        }

        return redirect()->route('admin.machines.index')->with(
            'success',
            "Machine deleted. {$machineTypesCount} machine type(s), {$designsCount} design(s), and {$usersCount} user assignment(s) were safely disconnected.",
        );
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
            'designFormConfig.title' => ['sometimes', 'boolean'],
            'designFormConfig.machineType' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.category' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.designTypes' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.designSpecifications' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.designTypeImageCards' => ['sometimes', 'boolean'],
            'designFormConfig.sequenceType' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.designFileType' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.previewImages' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.stitchFiles' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.pricing' => ['required_with:designFormConfig', 'boolean'],
            'designFormConfig.required' => ['sometimes', 'array'],
            'designFormConfig.required.title' => ['sometimes', 'boolean'],
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
            'designFormConfig.specificationFields.image' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.nameOrNumber' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.stitchCount' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.needle' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.area' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.height' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.width' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required' => ['sometimes', 'array'],
            'designFormConfig.specificationFields.required.image' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required.nameOrNumber' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required.stitchCount' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required.needle' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required.area' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required.height' => ['sometimes', 'boolean'],
            'designFormConfig.specificationFields.required.width' => ['sometimes', 'boolean'],
            'watermarkConfig' => ['sometimes', 'array'],
            'watermarkConfig.enabled' => ['required_with:watermarkConfig', 'boolean'],
            'watermarkConfig.text' => ['required_if:watermarkConfig.enabled,true', 'nullable', 'string', 'max:60'],
            'watermarkConfig.opacity' => ['required_if:watermarkConfig.enabled,true', 'integer', 'min:5', 'max:60'],
            'watermarkConfig.textSize' => ['sometimes', 'integer', 'min:50', 'max:400'],
            'watermarkConfig.spacing' => ['sometimes', 'integer', 'min:50', 'max:250'],
        ]);

        return [
            'name' => trim($validated['name']),
            'is_active' => $request->boolean('isActive', true),
            'sort_order' => isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : null,
            'design_form_config' => MachineDesignFormConfig::normalize($validated['designFormConfig'] ?? null),
            'watermark_config' => [
                'enabled' => (bool) data_get($validated, 'watermarkConfig.enabled', false),
                'text' => trim((string) data_get($validated, 'watermarkConfig.text', 'Textile Design Hub')) ?: 'Textile Design Hub',
                'opacity' => (int) data_get($validated, 'watermarkConfig.opacity', 24),
                'textSize' => (int) data_get($validated, 'watermarkConfig.textSize', 100),
                'spacing' => (int) data_get($validated, 'watermarkConfig.spacing', 100),
            ],
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
