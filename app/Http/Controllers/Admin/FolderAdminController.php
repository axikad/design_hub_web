<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Services\FolderArchiveService;
use App\Support\FolderFilePreview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FolderAdminController extends Controller
{
    public function __construct(private readonly FolderArchiveService $archives) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $folders = Folder::query()
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('zip_name', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Folder $folder) => [
                'id' => $folder->public_id,
                'name' => $folder->name,
                'priceRupees' => number_format($folder->price_paise / 100, 2, '.', ''),
                'zipName' => $folder->zip_name,
                'fileCount' => $folder->file_count,
                'totalSize' => $folder->total_size,
                'updatedAt' => $folder->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/folders/index', [
            'folders' => $folders,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/folders/form', ['folder' => null]);
    }

    public function show(string $folder): Response
    {
        $model = $this->find($folder);

        return Inertia::render('admin/folders/show', [
            'folder' => $this->folderPayload($model),
        ]);
    }

    public function preview(string $folder, int $file): BinaryFileResponse
    {
        $model = $this->find($folder);
        $folderFile = $model->files()->whereKey($file)->firstOrFail();
        $mimeType = FolderFilePreview::mimeType($folderFile);

        abort_unless($mimeType !== null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($folderFile->storage_path), 404);

        $safeName = str_replace(['"', "\r", "\n"], '', $folderFile->name);

        return response()->file($disk->path($folderFile->storage_path), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
            'Content-Security-Policy' => "sandbox; default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, true);
        $folder = Folder::query()->create([
            'name' => $validated['name'],
            'price_paise' => $this->pricePaise($validated['priceRupees']),
            'zip_name' => '',
            'zip_path' => '',
        ]);

        try {
            $archive = $this->archives->extract($request->file('zipFile'), $folder);

            DB::transaction(function () use ($folder, $archive): void {
                $folder->update(collect($archive)->except('files')->all());
                $folder->files()->createMany($archive['files']);
            });
        } catch (RuntimeException $exception) {
            $this->archives->deleteFolder($folder);
            $folder->delete();

            throw ValidationException::withMessages(['zipFile' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            $this->archives->deleteFolder($folder);
            $folder->delete();
            report($exception);

            throw ValidationException::withMessages(['zipFile' => 'The ZIP archive could not be processed.']);
        }

        return redirect()->route('admin.folders.index')->with('success', 'Folder created and ZIP files extracted.');
    }

    public function edit(string $folder): Response
    {
        $model = $this->find($folder);

        return Inertia::render('admin/folders/form', [
            'folder' => $this->folderPayload($model),
        ]);
    }

    public function update(Request $request, string $folder): RedirectResponse
    {
        $model = $this->find($folder);
        $validated = $this->validated($request, false, $model);
        $archive = null;

        try {
            if ($request->hasFile('zipFile')) {
                $archive = $this->archives->extract($request->file('zipFile'), $model);
            }

            $oldZipPath = $model->zip_path;

            DB::transaction(function () use ($model, $validated, $archive): void {
                $attributes = [
                    'name' => $validated['name'],
                    'price_paise' => $this->pricePaise($validated['priceRupees']),
                ];

                if ($archive !== null) {
                    $model->files()->delete();
                    $attributes = [...$attributes, ...collect($archive)->except('files')->all()];
                    $model->files()->createMany($archive['files']);
                }

                $model->update($attributes);
            });

            if ($archive !== null && $oldZipPath !== $model->zip_path) {
                $this->archives->deleteArchiveVersion($oldZipPath);
            }
        } catch (RuntimeException $exception) {
            if ($archive !== null) {
                $this->archives->deleteArchiveVersion($archive['zip_path']);
            }

            throw ValidationException::withMessages(['zipFile' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            if ($archive !== null) {
                $this->archives->deleteArchiveVersion($archive['zip_path']);
            }

            report($exception);
            throw ValidationException::withMessages(['zipFile' => 'The ZIP archive could not be processed.']);
        }

        return redirect()->route('admin.folders.index')->with('success', 'Folder updated.');
    }

    public function destroy(string $folder): RedirectResponse
    {
        $model = $this->find($folder);
        $this->archives->deleteFolder($model);
        $model->delete();

        return redirect()->route('admin.folders.index')->with('success', 'Folder and its stored files deleted.');
    }

    private function find(string $publicId): Folder
    {
        return Folder::query()->where('public_id', $publicId)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function folderPayload(Folder $folder): array
    {
        return [
            'id' => $folder->public_id,
            'name' => $folder->name,
            'priceRupees' => number_format($folder->price_paise / 100, 2, '.', ''),
            'zipName' => $folder->zip_name,
            'fileCount' => $folder->file_count,
            'totalSize' => $folder->total_size,
            'createdAt' => $folder->created_at?->toIso8601String(),
            'updatedAt' => $folder->updated_at?->toIso8601String(),
            'files' => $folder->files()->get()->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->name,
                'relativePath' => $file->relative_path,
                'extension' => $file->extension,
                'mimeType' => $file->mime_type,
                'size' => $file->size,
                'previewUrl' => FolderFilePreview::mimeType($file) !== null
                    ? route('admin.folders.files.preview', [$folder->public_id, $file->id])
                    : null,
            ])->all(),
        ];
    }

    /** @return array{name: string, priceRupees: string} */
    private function validated(Request $request, bool $creating, ?Folder $folder = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'priceRupees' => trim((string) $request->input('priceRupees')),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('folders', 'name')->ignore($folder?->id)],
            'priceRupees' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'zipFile' => [$creating ? 'required' : 'nullable', 'file', 'extensions:zip'],
        ]);
    }

    private function pricePaise(string $price): int
    {
        return (int) round(((float) $price) * 100);
    }
}
