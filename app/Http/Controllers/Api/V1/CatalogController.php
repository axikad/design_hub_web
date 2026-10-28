<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DesignCategory;
use App\Models\DesignFileType;
use App\Models\Machine;
use App\Models\MachineType;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
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

        return ApiResponse::data($fileTypes->values(), message: 'Machine design file types retrieved successfully.');
    }

    public function machines()
    {
        $machines = Machine::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
                'imageUri' => $machine->imageUrl(),
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

    public function machineTypeCategories(string $machineTypeId)
    {
        $machineType = MachineType::query()
            ->where('public_id', $machineTypeId)
            ->where('is_active', true)
            ->firstOrFail();

        $categories = $machineType->categories()
            ->where('design_categories.is_active', true)
            ->orderBy('design_categories.sort_order')
            ->orderBy('design_categories.name')
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

    public function designOptions()
    {
        $machines = Machine::query()
            ->where('is_active', true)
            ->with([
                'machineTypes' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
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
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'machine_id', 'public_id', 'name']);

        $categories = DesignCategory::query()
            ->where('is_active', true)
            ->whereHas('machineTypes', fn ($query) => $query->where('machine_types.is_active', true))
            ->with(['machineTypes' => fn ($query) => $query
                ->where('machine_types.is_active', true)
                ->orderBy('machine_types.sort_order')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::data([
            'machines' => $machines->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
                'imageUri' => $machine->imageUrl(),
                'machineTypeIds' => $machine->machineTypes->pluck('public_id')->values(),
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
        $categories = DesignCategory::query()
            ->where('is_active', true)
            ->with(['activeTypes' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
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
}
