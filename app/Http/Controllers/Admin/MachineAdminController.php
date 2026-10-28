<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MachineAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $machines = Machine::query()
            ->withCount('machineTypes')
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
        return Inertia::render('admin/machines/form', ['machine' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $machine = Machine::query()->create($this->validated($request));
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
            ],
        ]);
    }

    public function update(Request $request, string $machine): RedirectResponse
    {
        $model = Machine::query()->where('public_id', $machine)->firstOrFail();
        $model->update($this->validated($request));
        $this->syncImage($request, $model);

        return redirect()->route('admin.machines.index')->with('success', 'Machine updated.');
    }

    public function destroy(string $machine): RedirectResponse
    {
        $model = Machine::query()->where('public_id', $machine)->firstOrFail();

        if ($model->machineTypes()->exists()) {
            return back()->withErrors(['machine' => 'Delete or reassign this machine\'s types first.']);
        }

        $imagePath = $model->image_path;
        $model->delete();

        if ($imagePath !== null) {
            Storage::disk('public')->delete($imagePath);
        }

        return redirect()->route('admin.machines.index')->with('success', 'Machine deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'isActive' => ['boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'removeImage' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim($validated['name']),
            'is_active' => $request->boolean('isActive', true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
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
