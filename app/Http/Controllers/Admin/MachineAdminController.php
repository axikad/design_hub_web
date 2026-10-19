<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MachineAdminController extends Controller
{
    public function index(): Response
    {
        $machines = Machine::query()
            ->withCount('machineTypes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
                'isActive' => $machine->is_active,
                'sortOrder' => $machine->sort_order,
                'machineTypesCount' => $machine->machine_types_count,
            ]);

        return Inertia::render('admin/machines/index', ['machines' => $machines]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/machines/form', ['machine' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Machine::query()->create($this->validated($request));

        return redirect()->route('admin.machines.index')->with('success', 'Machine created.');
    }

    public function edit(string $machine): Response
    {
        $model = Machine::query()->where('public_id', $machine)->firstOrFail();

        return Inertia::render('admin/machines/form', [
            'machine' => [
                'id' => $model->public_id,
                'name' => $model->name,
                'isActive' => $model->is_active,
                'sortOrder' => $model->sort_order,
            ],
        ]);
    }

    public function update(Request $request, string $machine): RedirectResponse
    {
        Machine::query()->where('public_id', $machine)->firstOrFail()->update($this->validated($request));

        return redirect()->route('admin.machines.index')->with('success', 'Machine updated.');
    }

    public function destroy(string $machine): RedirectResponse
    {
        $model = Machine::query()->where('public_id', $machine)->firstOrFail();

        if ($model->machineTypes()->exists()) {
            return back()->withErrors(['machine' => 'Delete or reassign this machine’s types first.']);
        }

        $model->delete();

        return redirect()->route('admin.machines.index')->with('success', 'Machine deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'isActive' => ['boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        return [
            'name' => trim($validated['name']),
            'is_active' => $request->boolean('isActive', true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ];
    }
}
