<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignFileType;
use App\Models\Machine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DesignFileTypeAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $fileTypes = DesignFileType::query()
            ->with(['machines' => fn ($query) => $query->orderBy('name')])
            ->withCount('designs')
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('extension', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DesignFileType $fileType) => [
                'id' => $fileType->public_id,
                'name' => $fileType->name,
                'extension' => $fileType->extension,
                'isActive' => $fileType->is_active,
                'sortOrder' => $fileType->sort_order,
                'designsCount' => $fileType->designs_count,
                'machineNames' => $fileType->machines->pluck('name')->values(),
            ]);

        return Inertia::render('admin/design-file-types/index', [
            'fileTypes' => $fileTypes,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/design-file-types/form', [
            'fileType' => null,
            'machines' => $this->machineOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        DB::transaction(function () use ($validated): void {
            $fileType = DesignFileType::query()->create($validated['attributes']);
            $fileType->machines()->sync($validated['machineIds']);
        });

        return redirect()->route('admin.design-file-types.index')->with('success', 'Design file type created.');
    }

    public function edit(string $designFileType): Response
    {
        $fileType = $this->find($designFileType)->load('machines');

        return Inertia::render('admin/design-file-types/form', [
            'fileType' => [
                'id' => $fileType->public_id,
                'name' => $fileType->name,
                'extension' => $fileType->extension,
                'isActive' => $fileType->is_active,
                'sortOrder' => $fileType->sort_order,
                'machineIds' => $fileType->machines->pluck('public_id')->values(),
            ],
            'machines' => $this->machineOptions(),
        ]);
    }

    public function update(Request $request, string $designFileType): RedirectResponse
    {
        $fileType = $this->find($designFileType);
        $validated = $this->validated($request, $fileType);
        DB::transaction(function () use ($fileType, $validated): void {
            $fileType->update($validated['attributes']);
            $fileType->machines()->sync($validated['machineIds']);
        });

        return redirect()->route('admin.design-file-types.index')->with('success', 'Design file type updated.');
    }

    public function destroy(string $designFileType): RedirectResponse
    {
        $fileType = $this->find($designFileType);

        if ($fileType->designs()->exists()) {
            return back()->withErrors([
                'designFileType' => 'Reassign designs before deleting this file type.',
            ]);
        }

        $fileType->delete();

        return redirect()->route('admin.design-file-types.index')->with('success', 'Design file type deleted.');
    }

    private function find(string $publicId): DesignFileType
    {
        return DesignFileType::query()->where('public_id', $publicId)->firstOrFail();
    }

    /** @return array{attributes: array{name: string, extension: string, is_active: bool, sort_order: int}, machineIds: list<int>} */
    private function validated(Request $request, ?DesignFileType $fileType = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'extension' => strtolower(ltrim(trim((string) $request->input('extension')), '.')),
        ]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('design_file_types', 'name')->ignore($fileType?->id),
            ],
            'extension' => [
                'required',
                'string',
                'max:20',
                'regex:/^\.?[A-Za-z0-9]+$/',
                Rule::unique('design_file_types', 'extension')->ignore($fileType?->id),
            ],
            'isActive' => ['boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'machineIds' => ['required', 'array', 'min:1'],
            'machineIds.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('machines', 'public_id')->where('is_active', true),
            ],
        ]);

        return [
            'attributes' => [
                'name' => trim($validated['name']),
                'extension' => strtolower(ltrim($validated['extension'], '.')),
                'is_active' => $request->boolean('isActive', true),
                'sort_order' => (int) ($validated['sortOrder'] ?? 0),
            ],
            'machineIds' => Machine::query()
                ->whereIn('public_id', $validated['machineIds'])
                ->pluck('id')
                ->all(),
        ];
    }

    /** @return list<array{id: string, name: string}> */
    private function machineOptions(): array
    {
        return Machine::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
            ])
            ->values()
            ->all();
    }
}
