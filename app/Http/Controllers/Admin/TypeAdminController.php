<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignCategory;
use App\Models\DesignType;
use App\Services\SortOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TypeAdminController extends Controller
{
    public function __construct(private readonly SortOrderService $sortOrder) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $categoryId = (string) $request->string('category');

        $types = DesignType::query()
            ->with(['categories' => fn ($query) => $query
                ->where('design_categories.is_active', true)
                ->orderBy('design_categories.sort_order')
                ->orderBy('design_categories.name')])
            ->withCount('designs')
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->when($categoryId !== '', fn ($query) => $query->whereHas(
                'categories',
                fn ($categoryQuery) => $categoryQuery->where('design_categories.public_id', $categoryId),
            ))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DesignType $type) => [
                'id' => $type->public_id,
                'name' => $type->name,
                'description' => $type->description,
                'isActive' => $type->is_active,
                'sortOrder' => $type->sort_order,
                'categoryNames' => $type->categories->pluck('name')->values(),
                'designsCount' => $type->designs_count,
            ]);

        return Inertia::render('admin/types/index', [
            'types' => $types,
            'categories' => $this->categoryOptions(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category' => $categoryId,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/types/form', [
            'type' => null,
            'categories' => $this->categoryOptions(),
            'nextSortOrder' => $this->sortOrder->nextPosition(DesignType::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated): void {
            $position = $validated['sortOrder'];
            $type = DesignType::query()->create([
                'design_category_id' => $validated['categoryIds'][0],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'is_active' => $validated['isActive'],
                'sort_order' => $position ?? $this->sortOrder->nextPosition(DesignType::class),
            ]);
            $this->sortOrder->place($type, $position);
            $this->syncCategoriesPreservingTypeOrder($type, $validated['categoryIds']);
        });

        return redirect()->route('admin.types.index')->with('success', 'Type created.');
    }

    public function edit(string $type): Response
    {
        $model = DesignType::query()
            ->with(['categories' => fn ($query) => $query->where('design_categories.is_active', true)])
            ->where('public_id', $type)
            ->firstOrFail();

        return Inertia::render('admin/types/form', [
            'type' => [
                'id' => $model->public_id,
                'categoryIds' => $model->categories->pluck('public_id')->values(),
                'name' => $model->name,
                'description' => $model->description ?? '',
                'isActive' => $model->is_active,
                'sortOrder' => $model->sort_order,
            ],
            'categories' => $this->categoryOptions(),
            'nextSortOrder' => $this->sortOrder->nextPosition(DesignType::class),
        ]);
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        $model = DesignType::query()->where('public_id', $type)->firstOrFail();
        $validated = $this->validated($request);

        DB::transaction(function () use ($model, $validated): void {
            $model->update([
                'design_category_id' => $validated['categoryIds'][0],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'is_active' => $validated['isActive'],
            ]);
            $this->sortOrder->place($model, $validated['sortOrder']);
            $this->syncCategoriesPreservingTypeOrder($model, $validated['categoryIds']);
        });

        return redirect()->route('admin.types.index')->with('success', 'Type updated.');
    }

    public function destroy(string $type): RedirectResponse
    {
        $model = DesignType::query()->where('public_id', $type)->firstOrFail();

        if ($model->designs()->exists()) {
            return back()->withErrors(['type' => 'Reassign designs before deleting this type.']);
        }

        $model->delete();
        $this->sortOrder->remove(DesignType::class);

        return redirect()->route('admin.types.index')->with('success', 'Type deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'distinct', Rule::exists('design_types', 'public_id')],
            'startPosition' => ['required', 'integer', 'min:1'],
        ]);

        $this->sortOrder->reorderPage(DesignType::class, $validated['ids'], $validated['startPosition']);

        return back()->with('success', 'Design type order updated.');
    }

    /** @return list<array{id: string, name: string}> */
    private function categoryOptions(): array
    {
        return DesignCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['public_id', 'name'])
            ->map(fn (DesignCategory $category) => [
                'id' => $category->public_id,
                'name' => $category->name,
            ])
            ->values()
            ->all();
    }

    /** @return array{categoryIds: list<int>, name: string, description: ?string, isActive: bool, sortOrder: ?int} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'categoryIds' => ['required', 'array', 'min:1'],
            'categoryIds.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('design_categories', 'public_id')->where('is_active', true),
            ],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'isActive' => ['boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $categories = DesignCategory::query()
            ->where('is_active', true)
            ->whereIn('public_id', $validated['categoryIds'])
            ->get(['id', 'public_id'])
            ->keyBy('public_id');

        return [
            'categoryIds' => collect($validated['categoryIds'])
                ->map(fn (string $publicId) => $categories->get($publicId)->id)
                ->all(),
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'isActive' => $request->boolean('isActive', true),
            'sortOrder' => isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : null,
        ];
    }

    /** @param  list<int>  $categoryIds */
    private function syncCategoriesPreservingTypeOrder(DesignType $type, array $categoryIds): void
    {
        $existingOrders = DB::table('category_design_type')
            ->where('design_type_id', $type->id)
            ->pluck('sort_order', 'design_category_id');

        $syncData = collect($categoryIds)->mapWithKeys(function (int $categoryId) use ($existingOrders): array {
            $existingPosition = (int) ($existingOrders->get($categoryId) ?? 0);
            $position = $existingPosition > 0
                ? $existingPosition
                : ((int) DB::table('category_design_type')->where('design_category_id', $categoryId)->max('sort_order') + 1);

            return [$categoryId => ['sort_order' => $position]];
        })->all();

        $type->categories()->sync($syncData);
    }
}
