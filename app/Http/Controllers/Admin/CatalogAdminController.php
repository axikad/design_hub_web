<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\User;
use App\Services\CatalogArchiveService;
use App\Support\CatalogFilePreview;
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

class CatalogAdminController extends Controller
{
    public function __construct(private readonly CatalogArchiveService $archives) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        $ownContentOnly = $actor->mustScopeMarketplaceContentToSelf();
        $search = trim((string) $request->string('search'));

        $catalogs = Catalog::query()
            ->with('owner')
            ->when($ownContentOnly, fn ($query) => $query->where('user_id', $actor->id))
            ->withCount(['purchases as paid_purchase_count' => fn ($query) => $query->where('status', 'paid')])
            ->withSum(['purchases as paid_revenue_paise' => fn ($query) => $query->where('status', 'paid')], 'amount_paise')
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('zip_name', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Catalog $catalog) => [
                'id' => $catalog->public_id,
                'name' => $catalog->name,
                'ownerName' => $catalog->owner?->name,
                'ownerPhone' => $catalog->owner === null ? null : trim($catalog->owner->country_code.$catalog->owner->mobile_number),
                'priceRupees' => number_format($catalog->price_paise / 100, 2, '.', ''),
                'zipName' => $catalog->zip_name,
                'fileCount' => $catalog->file_count,
                'totalSize' => $catalog->total_size,
                'paidPurchaseCount' => $catalog->paid_purchase_count,
                'revenuePaise' => (int) ($catalog->paid_revenue_paise ?? 0),
                'updatedAt' => $catalog->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/catalogs/index', [
            'catalogs' => $catalogs,
            'filters' => ['search' => $search],
            'ownContentOnly' => $ownContentOnly,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/catalogs/form', [
            'catalog' => null,
            'owners' => $this->ownerOptions($request->user()),
            'canChooseSeller' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function show(Request $request, string $catalog): Response
    {
        $model = $this->find($catalog, $request->user());

        return Inertia::render('admin/catalogs/show', [
            'catalog' => $this->catalogPayload($model),
        ]);
    }

    public function preview(Request $request, string $catalog, int $file): BinaryFileResponse
    {
        $model = $this->find($catalog, $request->user());
        $catalogFile = $model->files()->whereKey($file)->firstOrFail();
        $mimeType = CatalogFilePreview::mimeType($catalogFile);

        abort_unless($mimeType !== null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($catalogFile->storage_path), 404);

        $safeName = str_replace(['"', "\r", "\n"], '', $catalogFile->name);

        return response()->file($disk->path($catalogFile->storage_path), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
            'Content-Security-Policy' => "sandbox; default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, true);
        $owner = User::query()->where('public_id', $validated['ownerId'])->firstOrFail();
        $catalog = Catalog::query()->create([
            'user_id' => $owner->id,
            'name' => $validated['name'],
            'price_paise' => $this->pricePaise($validated['priceRupees']),
            'zip_name' => '',
            'zip_path' => '',
        ]);

        try {
            $archive = $this->archives->extract($request->file('zipFile'), $catalog);

            DB::transaction(function () use ($catalog, $archive): void {
                $catalog->update(collect($archive)->except('files')->all());
                $catalog->files()->createMany($archive['files']);
            });
        } catch (RuntimeException $exception) {
            $this->archives->deleteCatalog($catalog);
            $catalog->delete();

            throw ValidationException::withMessages(['zipFile' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            $this->archives->deleteCatalog($catalog);
            $catalog->delete();
            report($exception);

            throw ValidationException::withMessages(['zipFile' => 'The archive could not be processed.']);
        }

        return redirect()->route('admin.catalogs.index')->with('success', 'Catalog created and archive files extracted.');
    }

    public function edit(Request $request, string $catalog): Response
    {
        $model = $this->find($catalog, $request->user());

        return Inertia::render('admin/catalogs/form', [
            'catalog' => $this->catalogPayload($model),
            'owners' => $this->ownerOptions($request->user()),
            'canChooseSeller' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function update(Request $request, string $catalog): RedirectResponse
    {
        $model = $this->find($catalog, $request->user());
        $validated = $this->validated($request, false, $model);
        $archive = null;

        try {
            if ($request->hasFile('zipFile')) {
                $archive = $this->archives->extract($request->file('zipFile'), $model);
            }

            $oldZipPath = $model->zip_path;

            DB::transaction(function () use ($model, $validated, $archive): void {
                $attributes = [
                    'user_id' => User::query()->where('public_id', $validated['ownerId'])->value('id'),
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
            throw ValidationException::withMessages(['zipFile' => 'The archive could not be processed.']);
        }

        return redirect()->route('admin.catalogs.index')->with('success', 'Catalog updated.');
    }

    public function destroy(Request $request, string $catalog): RedirectResponse
    {
        $model = $this->find($catalog, $request->user());
        $this->archives->deleteCatalog($model);
        $model->delete();

        return redirect()->route('admin.catalogs.index')->with('success', 'Catalog and its stored files deleted.');
    }

    private function find(string $publicId, User $actor): Catalog
    {
        return Catalog::query()
            ->where('public_id', $publicId)
            ->when($actor->mustScopeMarketplaceContentToSelf(), fn ($query) => $query->where('user_id', $actor->id))
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function catalogPayload(Catalog $catalog): array
    {
        $catalog->load([
            'owner',
            'purchases' => fn ($query) => $query
                ->with(['buyer', 'paymentOrder'])
                ->latest('purchased_at'),
        ]);

        return [
            'id' => $catalog->public_id,
            'ownerId' => $catalog->owner?->public_id,
            'ownerName' => $catalog->owner?->name,
            'ownerPhone' => $catalog->owner === null ? null : trim($catalog->owner->country_code.$catalog->owner->mobile_number),
            'name' => $catalog->name,
            'priceRupees' => number_format($catalog->price_paise / 100, 2, '.', ''),
            'zipName' => $catalog->zip_name,
            'fileCount' => $catalog->file_count,
            'totalSize' => $catalog->total_size,
            'createdAt' => $catalog->created_at?->toIso8601String(),
            'updatedAt' => $catalog->updated_at?->toIso8601String(),
            'paidPurchaseCount' => $catalog->purchases->where('status', 'paid')->count(),
            'pendingPurchaseCount' => $catalog->purchases->where('status', 'pending')->count(),
            'revenuePaise' => (int) $catalog->purchases->where('status', 'paid')->sum('amount_paise'),
            'purchases' => $catalog->purchases->take(20)->map(fn ($purchase) => [
                'id' => $purchase->public_id,
                'buyerName' => $purchase->buyer?->name,
                'buyerPhone' => $purchase->buyer
                    ? $purchase->buyer->country_code.$purchase->buyer->mobile_number
                    : null,
                'amountPaise' => $purchase->amount_paise,
                'currency' => $purchase->currency,
                'status' => $purchase->status,
                'razorpayOrderId' => $purchase->paymentOrder?->razorpay_order_id,
                'razorpayPaymentId' => $purchase->razorpay_payment_id,
                'purchasedAt' => $purchase->purchased_at?->toIso8601String(),
            ])->values()->all(),
            'files' => $catalog->files()->get()->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->name,
                'relativePath' => $file->relative_path,
                'extension' => $file->extension,
                'mimeType' => $file->mime_type,
                'size' => $file->size,
                'previewUrl' => CatalogFilePreview::mimeType($file) !== null
                    ? route('admin.catalogs.files.preview', [$catalog->public_id, $file->id])
                    : null,
            ])->all(),
        ];
    }

    /** @return array{ownerId: string, name: string, priceRupees: string} */
    private function validated(Request $request, bool $creating, ?Catalog $catalog = null): array
    {
        $ownerId = $request->user()->isSuperAdmin()
            ? trim((string) $request->input('ownerId'))
            : ($catalog?->owner?->public_id ?? $request->user()->public_id);

        $request->merge([
            'ownerId' => $ownerId,
            'name' => trim((string) $request->input('name')),
            'priceRupees' => trim((string) $request->input('priceRupees')),
        ]);

        return $request->validate([
            'ownerId' => ['required', 'string', Rule::exists('users', 'public_id')],
            'name' => ['required', 'string', 'max:150', Rule::unique('catalogs', 'name')->ignore($catalog?->id)],
            'priceRupees' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'zipFile' => [$creating ? 'required' : 'nullable', 'file', 'extensions:zip,rar'],
        ]);
    }

    private function pricePaise(string $price): int
    {
        return (int) round(((float) $price) * 100);
    }

    /** @return list<array{id: string, label: string}> */
    private function ownerOptions(User $actor): array
    {
        if (! $actor->isSuperAdmin()) {
            return [[
                'id' => $actor->public_id,
                'label' => $actor->name,
            ]];
        }

        return User::query()
            ->whereNotNull('phone_key')
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->public_id,
                'label' => trim($user->name.' · '.$user->country_code.$user->mobile_number),
            ])
            ->values()
            ->all();
    }
}
