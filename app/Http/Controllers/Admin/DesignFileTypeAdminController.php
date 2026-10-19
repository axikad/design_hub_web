<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignFileType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DesignFileTypeAdminController extends Controller
{
    public function index(): Response
    {
        $fileTypes = DesignFileType::query()
            ->withCount('designs')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (DesignFileType $fileType) => [
                'id' => $fileType->public_id,
                'name' => $fileType->name,
                'extension' => $fileType->extension,
                'isActive' => $fileType->is_active,
                'sortOrder' => $fileType->sort_order,
                'designsCount' => $fileType->designs_count,
            ]);

        return Inertia::render('admin/design-file-types/index', [
            'fileTypes' => $fileTypes,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/design-file-types/form', [
            'fileType' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DesignFileType::query()->create($this->validated($request));

        return redirect()->route('admin.design-file-types.index')->with('success', 'Design file type created.');
    }

    public function edit(string $designFileType): Response
    {
        $fileType = $this->find($designFileType);

        return Inertia::render('admin/design-file-types/form', [
            'fileType' => [
                'id' => $fileType->public_id,
                'name' => $fileType->name,
                'extension' => $fileType->extension,
                'isActive' => $fileType->is_active,
                'sortOrder' => $fileType->sort_order,
            ],
        ]);
    }

    public function update(Request $request, string $designFileType): RedirectResponse
    {
        $fileType = $this->find($designFileType);
        $fileType->update($this->validated($request, $fileType));

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

    /** @return array{name: string, extension: string, is_active: bool, sort_order: int} */
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
        ]);

        return [
            'name' => trim($validated['name']),
            'extension' => strtolower(ltrim($validated['extension'], '.')),
            'is_active' => $request->boolean('isActive', true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ];
    }
}
