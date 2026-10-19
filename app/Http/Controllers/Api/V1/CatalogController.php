<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DesignCategory;
use App\Models\Machine;
use App\Models\MachineType;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function designOptions()
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
                'machineTypeIds' => $machine->machineTypes->pluck('public_id')->values(),
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
