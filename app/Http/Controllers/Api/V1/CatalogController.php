<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DesignCategory;
use App\Models\DesignFileType;
use App\Models\DesignType;
use App\Models\Machine;
use App\Models\MachineType;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CatalogController extends Controller
{
    public function browseFilters()
    {
        $machines = Machine::query()
            ->where('is_active', true)
            ->with(['machineTypes' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $machineTypes = MachineType::query()
            ->where('is_active', true)
            ->whereHas('machine', fn ($query) => $query->where('is_active', true))
            ->with([
                'machine:id,public_id',
                'categories' => fn ($query) => $query->where('design_categories.is_active', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = DesignCategory::query()
            ->where('is_active', true)
            ->whereHas('machineTypes', fn ($query) => $query
                ->where('machine_types.is_active', true)
                ->whereHas('machine', fn ($machineQuery) => $machineQuery->where('is_active', true)))
            ->with([
                'machineTypes' => fn ($query) => $query
                    ->where('machine_types.is_active', true)
                    ->whereHas('machine', fn ($machineQuery) => $machineQuery->where('is_active', true)),
                'activeTypes',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $designTypes = DesignType::query()
            ->where('is_active', true)
            ->whereHas('categories', fn ($query) => $query
                ->where('design_categories.is_active', true)
                ->whereHas('machineTypes', fn ($machineTypeQuery) => $machineTypeQuery
                    ->where('machine_types.is_active', true)
                    ->whereHas('machine', fn ($machineQuery) => $machineQuery->where('is_active', true))))
            ->with(['categories' => fn ($query) => $query
                ->where('design_categories.is_active', true)
                ->whereHas('machineTypes', fn ($machineTypeQuery) => $machineTypeQuery
                    ->where('machine_types.is_active', true)
                    ->whereHas('machine', fn ($machineQuery) => $machineQuery->where('is_active', true)))])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::data([
            'machines' => $machines->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
                'imageUri' => $machine->imageUrl(),
                'machineTypeIds' => $machine->machineTypes->pluck('public_id')->values(),
            ])->values(),
            'machineTypes' => $machineTypes->map(fn (MachineType $machineType) => [
                'id' => $machineType->public_id,
                'name' => $machineType->name,
                'machineId' => $machineType->machine?->public_id,
                'categoryIds' => $machineType->categories->pluck('public_id')->values(),
            ])->values(),
            'categories' => $categories->map(fn (DesignCategory $category) => [
                'id' => $category->public_id,
                'name' => $category->name,
                'machineTypeIds' => $category->machineTypes->pluck('public_id')->values(),
                'designTypeIds' => $category->activeTypes->pluck('public_id')->values(),
            ])->values(),
            'designTypes' => $designTypes->map(fn (DesignType $designType) => [
                'id' => $designType->public_id,
                'name' => $designType->name,
                'categoryIds' => $designType->categories->pluck('public_id')->values(),
            ])->values(),
        ], message: 'Browse filters retrieved successfully.');
    }

    public function designFileTypes(Request $request)
    {
        $machineId = trim((string) $request->string('machineId'));
        $fileTypes = DesignFileType::query()
            ->where('is_active', true)
            ->when($machineId !== '', fn ($query) => $query->whereHas(
                'machines',
                fn ($machineQuery) => $machineQuery
                    ->where('machines.public_id', $machineId)
                    ->where('machines.is_active', true),
            ))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (DesignFileType $fileType) => [
                'id' => $fileType->public_id,
                'name' => $fileType->name,
                'extension' => $fileType->extension,
            ]);

        return ApiResponse::data($fileTypes->values(), message: 'Design file types retrieved successfully.');
    }

    public function machineDesignFileTypes(string $machineId)
    {
        $machine = Machine::query()
            ->where('public_id', $machineId)
            ->where('is_active', true)
            ->firstOrFail();

        $fileTypes = $machine->designFileTypes()
            ->where('design_file_types.is_active', true)
            ->orderBy('design_file_types.sort_order')
            ->orderBy('design_file_types.name')
            ->get()
            ->map(fn (DesignFileType $fileType) => [
                'id' => $fileType->public_id,
                'name' => $fileType->name,
                'extension' => $fileType->extension,
                'machineId' => $machine->public_id,
            ]);

        Log::info('catalog.machine_design_file_types', [
            'machine_id' => $machine->public_id,
            'machine_name' => $machine->name,
            'design_file_type_enabled' => (bool) ($machine->normalizedDesignFormConfig()['designFileType'] ?? true),
            'file_type_count' => $fileTypes->count(),
            'file_type_ids' => $fileTypes->pluck('id')->values()->all(),
        ]);

        return ApiResponse::data($fileTypes->values(), message: 'Machine design file types retrieved successfully.');
    }

    public function machines(Request $request)
    {
        $selectedMachineIds = $this->selectedMachineIds($request);

        $machines = Machine::query()
            ->where('is_active', true)
            ->when($selectedMachineIds->isNotEmpty(), fn ($query) => $query->whereIn('public_id', $selectedMachineIds))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
                'imageUri' => $machine->imageUrl(),
                'designFormConfig' => $machine->normalizedDesignFormConfig(),
                'watermarkEnabled' => $machine->normalizedWatermarkConfig()['enabled'],
            ]);

        return ApiResponse::data($machines->values(), message: 'Machines retrieved successfully.');
    }

    public function machineTypes(string $machineId)
    {
        $machine = Machine::query()
            ->where('public_id', $machineId)
            ->where('is_active', true)
            ->firstOrFail();

        $machineTypes = $machine->machineTypes()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MachineType $machineType) => [
                'id' => $machineType->public_id,
                'name' => $machineType->name,
                'machineId' => $machine->public_id,
            ]);

        return ApiResponse::data($machineTypes->values(), message: 'Machine types retrieved successfully.');
    }

    public function machineCategories(string $machineId)
    {
        $machine = Machine::query()
            ->where('public_id', $machineId)
            ->where('is_active', true)
            ->firstOrFail();

        $categories = DesignCategory::query()
            ->where('is_active', true)
            ->whereHas('machineTypes', fn ($query) => $query
                ->where('machine_types.machine_id', $machine->id)
                ->where('machine_types.is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (DesignCategory $category) => [
                'id' => $category->public_id,
                'name' => $category->name,
                'machineId' => $machine->public_id,
            ]);

        return ApiResponse::data($categories->values(), message: 'Machine categories retrieved successfully.');
    }

    public function machineTypeCategories(string $machineTypeId)
    {
        $machineType = MachineType::query()
            ->where('public_id', $machineTypeId)
            ->where('is_active', true)
            ->firstOrFail();

        $categories = $machineType->categories()
            ->where('design_categories.is_active', true)
            ->get()
            ->map(fn (DesignCategory $category) => [
                'id' => $category->public_id,
                'name' => $category->name,
                'machineTypeId' => $machineType->public_id,
            ]);

        return ApiResponse::data($categories->values(), message: 'Categories retrieved successfully.');
    }

    public function categoryTypes(string $categoryId)
    {
        $category = DesignCategory::query()
            ->where('public_id', $categoryId)
            ->where('is_active', true)
            ->firstOrFail();

        $types = $category->activeTypes
            ->map(fn ($type) => [
                'id' => $type->public_id,
                'name' => $type->name,
                'description' => $type->description,
                'categoryId' => $category->public_id,
            ]);

        return ApiResponse::data($types->values(), message: 'Design types retrieved successfully.');
    }

    public function designOptions(Request $request)
    {
        $selectedMachineIds = $this->selectedMachineIds($request);

        $machines = Machine::query()
            ->where('is_active', true)
            ->when($selectedMachineIds->isNotEmpty(), fn ($query) => $query->whereIn('public_id', $selectedMachineIds))
            ->with([
                'machineTypes' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'machineTypes.categories' => fn ($query) => $query
                    ->where('design_categories.is_active', true),
                'designFileTypes' => fn ($query) => $query
                    ->where('design_file_types.is_active', true)
                    ->orderBy('design_file_types.sort_order')
                    ->orderBy('design_file_types.name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $machineTypes = MachineType::query()
            ->where('is_active', true)
            ->when($selectedMachineIds->isNotEmpty(), fn ($query) => $query->whereHas(
                'machine',
                fn ($machineQuery) => $machineQuery->whereIn('public_id', $selectedMachineIds),
            ))
            ->with('machine')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'machine_id', 'public_id', 'name']);

        $categories = DesignCategory::query()
            ->where('is_active', true)
            ->whereHas('machineTypes', fn ($query) => $query->where('machine_types.is_active', true))
            ->when($selectedMachineIds->isNotEmpty(), fn ($query) => $query->whereHas(
                'machineTypes.machine',
                fn ($machineQuery) => $machineQuery->whereIn('machines.public_id', $selectedMachineIds),
            ))
            ->with(['machineTypes' => fn ($query) => $query
                ->where('machine_types.is_active', true)
                ->when($selectedMachineIds->isNotEmpty(), fn ($machineTypeQuery) => $machineTypeQuery->whereHas(
                    'machine',
                    fn ($machineQuery) => $machineQuery->whereIn('machines.public_id', $selectedMachineIds),
                ))
                ->orderBy('machine_types.sort_order')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::data([
            'machines' => $machines->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
                'imageUri' => $machine->imageUrl(),
                'designFormConfig' => $machine->normalizedDesignFormConfig(),
                'watermarkEnabled' => $machine->normalizedWatermarkConfig()['enabled'],
                'machineTypeIds' => $machine->machineTypes->pluck('public_id')->values(),
                'categoryIds' => $machine->machineTypes
                    ->flatMap->categories
                    ->pluck('public_id')
                    ->unique()
                    ->values(),
                'designFileTypeIds' => $machine->designFileTypes->pluck('public_id')->values(),
            ])->values(),
            'machineTypes' => $machineTypes->map(fn (MachineType $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
                'machineId' => $machine->machine?->public_id,
            ])->values(),
            'categories' => $categories->map(fn (DesignCategory $category) => [
                'id' => $category->public_id,
                'name' => $category->name,
                'machineTypeIds' => $category->machineTypes->pluck('public_id')->values(),
            ])->values(),
        ], message: 'Design options retrieved successfully.');
    }

    public function categories(Request $request)
    {
        $selectedMachineIds = $this->selectedMachineIds($request);

        $categories = DesignCategory::query()
            ->where('is_active', true)
            ->when($selectedMachineIds->isNotEmpty(), fn ($query) => $query->whereHas(
                'machineTypes.machine',
                fn ($machineQuery) => $machineQuery->whereIn('machines.public_id', $selectedMachineIds),
            ))
            ->with('activeTypes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (DesignCategory $category) => [
                'id' => $category->public_id,
                'name' => $category->name,
                'description' => $category->description,
                'types' => $category->activeTypes->map(fn ($type) => [
                    'id' => $type->public_id,
                    'name' => $type->name,
                    'description' => $type->description,
                ])->values(),
            ]);

        return ApiResponse::data($categories->values(), message: 'Catalog retrieved successfully.');
    }

    private function selectedMachineIds(Request $request)
    {
        $user = $request->user();

        if ($user === null) {
            return collect();
        }

        $user->loadMissing('machines:id,public_id');

        return $user->machines->pluck('public_id');
    }
}
