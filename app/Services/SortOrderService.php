<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SortOrderService
{
    /** @param class-string<Model> $modelClass */
    public function nextPosition(string $modelClass, array $scope = []): int
    {
        return $this->query($modelClass, $scope)->count() + 1;
    }

    public function place(Model $model, ?int $requestedPosition, array $oldScope = [], array $newScope = []): void
    {
        DB::transaction(function () use ($model, $requestedPosition, $oldScope, $newScope): void {
            if ($oldScope !== $newScope) {
                $this->normalize($model::class, $oldScope, $model->getKey());
            }

            $items = $this->ordered($model::class, $newScope, $model->getKey());
            $position = max(1, min($requestedPosition ?? ($items->count() + 1), $items->count() + 1));
            $items->splice($position - 1, 0, [$model]);
            $this->savePositions($items);
        });
    }

    /** @param class-string<Model> $modelClass */
    public function remove(string $modelClass, array $scope = []): void
    {
        $this->normalize($modelClass, $scope);
    }

    /**
     * Reorder one visible page while preserving records on other pages.
     *
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $publicIds
     */
    public function reorderPage(string $modelClass, array $publicIds, int $startPosition = 1, array $scope = []): void
    {
        DB::transaction(function () use ($modelClass, $publicIds, $startPosition, $scope): void {
            $ordered = $this->ordered($modelClass, $scope);
            $byPublicId = $ordered->keyBy('public_id');
            $moved = collect($publicIds)->map(fn (string $id) => $byPublicId->get($id))->filter()->values();

            abort_unless($moved->count() === count($publicIds), 422, 'Some records could not be reordered.');

            $remaining = $ordered->reject(fn (Model $item) => in_array($item->public_id, $publicIds, true))->values();
            $insertAt = max(0, min($startPosition - 1, $remaining->count()));
            $remaining->splice($insertAt, 0, $moved->all());
            $this->savePositions($remaining);
        });
    }

    /** @param class-string<Model> $modelClass */
    private function normalize(string $modelClass, array $scope = [], int|string|null $exceptKey = null): void
    {
        $this->savePositions($this->ordered($modelClass, $scope, $exceptKey));
    }

    /** @param class-string<Model> $modelClass */
    private function ordered(string $modelClass, array $scope = [], int|string|null $exceptKey = null)
    {
        return $this->query($modelClass, $scope)
            ->when($exceptKey !== null, fn (Builder $query) => $query->whereKeyNot($exceptKey))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** @param class-string<Model> $modelClass */
    private function query(string $modelClass, array $scope): Builder
    {
        $query = $modelClass::query();

        foreach ($scope as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }

    private function savePositions($items): void
    {
        $items->values()->each(function (Model $item, int $index): void {
            $position = $index + 1;

            if ((int) $item->sort_order !== $position) {
                $item->newQuery()->whereKey($item->getKey())->update(['sort_order' => $position]);
                $item->sort_order = $position;
            }
        });
    }
}
