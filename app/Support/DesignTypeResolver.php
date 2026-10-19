<?php

namespace App\Support;

use App\Models\DesignCategory;
use App\Models\DesignType;
use App\Models\MachineType;

final class DesignTypeResolver
{
    public static function idFromPublicId(string $publicId): int
    {
        $type = DesignType::query()
            ->where('public_id', $publicId)
            ->where('is_active', true)
            ->first();

        if ($type === null) {
            throw new \InvalidArgumentException('Selected type is invalid or inactive.');
        }

        return $type->id;
    }

    /** @return array{design_type_id: int|null, category_id: string|null, machine_type_id: string|null} */
    public static function resolveMobileSelection(
        string $typeId,
        ?string $categoryId,
        ?string $machineTypeId,
    ): array {
        $optionCategory = DesignCategory::query()
            ->where('public_id', $typeId)
            ->where('is_active', true)
            ->first();

        if ($optionCategory !== null) {
            if ($categoryId !== $typeId) {
                throw new \InvalidArgumentException('categoryId and typeId must use the same design option ID.');
            }

            $machine = MachineType::query()
                ->where('public_id', $machineTypeId)
                ->where('is_active', true)
                ->first();

            if ($machine === null || ! $optionCategory->machineTypes()->whereKey($machine->id)->exists()) {
                throw new \InvalidArgumentException('The selected category is not available for this machine type.');
            }

            return [
                'design_type_id' => null,
                'category_id' => $optionCategory->public_id,
                'machine_type_id' => $machine->public_id,
            ];
        }

        $type = DesignType::query()
            ->with('category')
            ->where('public_id', $typeId)
            ->where('is_active', true)
            ->first();

        if ($type === null || $type->category === null || ! $type->category->is_active) {
            throw new \InvalidArgumentException('Selected type is invalid or inactive.');
        }

        if ($categoryId !== $type->category->public_id) {
            throw new \InvalidArgumentException('The selected type does not belong to this category.');
        }

        $machine = MachineType::query()
            ->where('public_id', $machineTypeId)
            ->where('is_active', true)
            ->first();

        if ($machine === null || ! $type->category->machineTypes()->whereKey($machine->id)->exists()) {
            throw new \InvalidArgumentException('The selected category is not available for this machine type.');
        }

        return [
            'design_type_id' => $type->id,
            'category_id' => $type->category->public_id,
            'machine_type_id' => $machine->public_id,
        ];
    }
}
