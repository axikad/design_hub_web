<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DesignCategory;
use App\Models\DesignMachineType;
use App\Models\DesignOptionCategory;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function designOptions()
    {
        $machineTypes = DesignMachineType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'public_id', 'name']);

        $categories = DesignOptionCategory::query()
            ->where('is_active', true)
            ->with(['machineTypes' => fn ($query) => $query
                ->where('design_machine_types.is_active', true)
                ->orderBy('design_machine_types.sort_order')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::data([
            'machineTypes' => $machineTypes->map(fn (DesignMachineType $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
            ])->values(),
            'categories' => $categories->map(fn (DesignOptionCategory $category) => [
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
