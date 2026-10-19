<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignCategory;
use App\Models\DesignType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TypeAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $categoryId = (string) $request->string('category');

        $types = DesignType::query()
            ->with('category')
            ->withCount('designs')
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->when($categoryId !== '', fn ($query) => $query->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('public_id', $categoryId),
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
                'categoryId' => $type->category?->public_id,
                'categoryName' => $type->category?->name,
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DesignType::query()->create([
            'design_category_id' => $validated['categoryId'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'is_active' => $validated['isActive'],
            'sort_order' => $validated['sortOrder'],
        ]);

        return redirect()->route('admin.types.index')->with('success', 'Type created.');
    }

    public function edit(string $type): Response
    {
        $model = DesignType::query()->with('category')->where('public_id', $type)->firstOrFail();

        return Inertia::render('admin/types/form', [
            'type' => [
                'id' => $model->public_id,
                'categoryId' => $model->category?->public_id,
                'name' => $model->name,
                'description' => $model->description ?? '',
                'isActive' => $model->is_active,
                'sortOrder' => $model->sort_order,
            ],
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        $model = DesignType::query()->where('public_id', $type)->firstOrFail();
        $validated = $this->validated($request);

        $model->update([
            'design_category_id' => $validated['categoryId'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'is_active' => $validated['isActive'],
            'sort_order' => $validated['sortOrder'],
        ]);

        return redirect()->route('admin.types.index')->with('success', 'Type updated.');
    }

    public function destroy(string $type): RedirectResponse
    {
        $model = DesignType::query()->where('public_id', $type)->firstOrFail();

        if ($model->designs()->exists()) {
            return back()->withErrors(['type' => 'Reassign designs before deleting this type.']);
        }

        $model->delete();

        return redirect()->route('admin.types.index')->with('success', 'Type deleted.');
    }

    /** @return list<array{id: string, name: string}> */
    private function categoryOptions(): array
    {
        return DesignCategory::query()
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

    /** @return array{categoryId: int, name: string, description: ?string, isActive: bool, sortOrder: int} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'categoryId' => ['required', 'string', Rule::exists('design_categories', 'public_id')],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'isActive' => ['boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $category = DesignCategory::query()->where('public_id', $validated['categoryId'])->firstOrFail();

        return [
            'categoryId' => $category->id,
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'isActive' => $request->boolean('isActive', true),
            'sortOrder' => (int) ($validated['sortOrder'] ?? 0),
        ];
    }
}
