<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\DesignCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $categories = DesignCategory::query()
            ->withCount(['types', 'machineTypes'])
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DesignCategory $category) => [
                'id' => $category->public_id,
                'name' => $category->name,
                'description' => $category->description,
                'isActive' => $category->is_active,
                'sortOrder' => $category->sort_order,
                'typesCount' => $category->types_count,
                'machineTypesCount' => $category->machine_types_count,
            ]);

        return Inertia::render('admin/categories/index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/categories/form', ['category' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        DesignCategory::query()->create($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(string $category): Response
    {
        $model = DesignCategory::query()->where('public_id', $category)->firstOrFail();

        return Inertia::render('admin/categories/form', [
            'category' => [
                'id' => $model->public_id,
                'name' => $model->name,
                'description' => $model->description ?? '',
                'isActive' => $model->is_active,
                'sortOrder' => $model->sort_order,
            ],
        ]);
    }

    public function update(Request $request, string $category): RedirectResponse
    {
        $model = DesignCategory::query()->where('public_id', $category)->firstOrFail();
        $model->update($this->validated($request));

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(string $category): RedirectResponse
    {
        $model = DesignCategory::query()->where('public_id', $category)->firstOrFail();

        if ($model->types()->exists() || $model->machineTypes()->exists() || Design::query()->where('category_id', $model->public_id)->exists()) {
            return back()->withErrors(['category' => 'Remove or reassign related machine types, design types, and designs before deleting this category.']);
        }

        $model->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'isActive' => ['boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        return [
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'is_active' => $request->boolean('isActive', true),
            'sort_order' => $validated['sortOrder'] ?? 0,
        ];
    }
}
