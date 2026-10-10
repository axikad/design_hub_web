<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\User;
use App\Support\DesignTypeResolver;
use App\Services\DesignPresenter;
use App\Support\DesignCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DesignAdminController extends Controller
{
    public function __construct(private readonly DesignPresenter $presenter) {}

    public function index(): Response
    {
        $designs = Design::query()
            ->listed()
            ->with(['owner', 'type.category'])
            ->latest('created_at')
            ->paginate(20)
            ->through(fn (Design $design) => array_merge(
                $this->presenter->forUser($design, $design->owner),
                ['ownerName' => $design->owner?->name],
            ));

        return Inertia::render('admin/designs/index', [
            'designs' => $designs,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/designs/form', [
            'design' => null,
            'owners' => $this->ownerOptions(),
            'sizeOptions' => DesignCatalog::sizes(),
            'colorOptions' => DesignCatalog::colors(),
            'catalog' => $this->catalogForForm(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateDesign($request, true);

        $owner = User::query()->where('public_id', $validated['ownerId'])->firstOrFail();

        $previewPath = $request->file('previewImage')?->store('previews/'.$owner->public_id, 'public');
        $stitchPath = $request->file('stitchFile')?->store('stitch/'.$owner->public_id, 'local');

        Design::query()->create([
            'user_id' => $owner->id,
            'design_type_id' => $validated['designTypeId'],
            'title' => $validated['title'],
            'price_paise' => $validated['pricePaise'],
            'size' => $validated['size'],
            'color' => $validated['color'],
            'preview_path' => $previewPath,
            'stitch_path' => $stitchPath,
            'file_name' => $request->file('stitchFile')?->getClientOriginalName(),
        ]);

        return redirect()->route('admin.designs.index')->with('success', 'Design created.');
    }

    public function edit(string $design): Response
    {
        $model = Design::query()->listed()->with(['owner', 'type'])->where('public_id', $design)->firstOrFail();

        return Inertia::render('admin/designs/form', [
            'design' => [
                'id' => $model->public_id,
                'ownerId' => $model->owner?->public_id,
                'typeId' => $model->type?->public_id,
                'title' => $model->title,
                'priceRupees' => $model->price_paise !== null ? number_format($model->price_paise / 100, 2, '.', '') : '',
                'size' => $model->size,
                'color' => $model->color,
                'fileName' => $model->file_name,
                'imageUri' => $this->presenter->previewUrl($model),
            ],
            'owners' => $this->ownerOptions(),
            'sizeOptions' => DesignCatalog::sizes(),
            'colorOptions' => DesignCatalog::colors(),
            'catalog' => $this->catalogForForm(),
        ]);
    }

    public function update(Request $request, string $design): RedirectResponse
    {
        $model = Design::query()->listed()->where('public_id', $design)->firstOrFail();
        $validated = $this->validateDesign($request, false);

        $owner = User::query()->where('public_id', $validated['ownerId'])->firstOrFail();

        $previewPath = $model->preview_path;
        if ($request->hasFile('previewImage')) {
            if ($previewPath) {
                Storage::disk('public')->delete($previewPath);
            }
            $previewPath = $request->file('previewImage')->store('previews/'.$owner->public_id, 'public');
        }

        $stitchPath = $model->stitch_path;
        $fileName = $model->file_name;
        if ($request->hasFile('stitchFile')) {
            if ($stitchPath) {
                Storage::disk('local')->delete($stitchPath);
            }
            $stitchPath = $request->file('stitchFile')->store('stitch/'.$owner->public_id, 'local');
            $fileName = $request->file('stitchFile')->getClientOriginalName();
        }

        $model->update([
            'user_id' => $owner->id,
            'design_type_id' => $validated['designTypeId'],
            'title' => $validated['title'],
            'price_paise' => $validated['pricePaise'],
            'size' => $validated['size'],
            'color' => $validated['color'],
            'preview_path' => $previewPath,
            'stitch_path' => $stitchPath,
            'file_name' => $fileName,
        ]);

        return redirect()->route('admin.designs.index')->with('success', 'Design updated.');
    }

    public function destroy(string $design): RedirectResponse
    {
        $model = Design::query()->listed()->where('public_id', $design)->firstOrFail();
        $model->update(['deleted_at' => now()]);

        return redirect()->route('admin.designs.index')->with('success', 'Design removed from marketplace.');
    }

    /** @return list<array{id: string, label: string}> */
    private function ownerOptions(): array
    {
        return User::query()
            ->whereNotNull('phone_key')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->public_id,
                'label' => trim($user->name.' · '.$user->country_code.$user->mobile_number),
            ])
            ->values()
            ->all();
    }

    /** @return array{ownerId: string, title: string, pricePaise: int, size: string, color: string} */
    private function validateDesign(Request $request, bool $creating): array
    {
        $rules = [
            'ownerId' => ['required', 'string', Rule::exists('users', 'public_id')],
            'typeId' => ['required', 'string', Rule::exists('design_types', 'public_id')],
            'title' => ['required', 'string', 'min:1', 'max:40'],
            'priceRupees' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'size' => ['required', 'string', 'max:120'],
            'color' => ['required', 'string', 'max:120'],
            'previewImage' => [$creating ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'stitchFile' => [$creating ? 'required' : 'nullable', 'file', 'max:5120'],
        ];

        $validated = $request->validate($rules);
        $pricePaise = (int) round(((float) $validated['priceRupees']) * 100);

        if ($pricePaise < 100 || $pricePaise > 100000000) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'priceRupees' => 'Price must be between ₹1 and ₹10,00,000.',
            ]);
        }

        try {
            $designTypeId = DesignTypeResolver::idFromPublicId($validated['typeId']);
        } catch (\InvalidArgumentException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'typeId' => $exception->getMessage(),
            ]);
        }

        return [
            'ownerId' => $validated['ownerId'],
            'designTypeId' => $designTypeId,
            'title' => trim($validated['title']),
            'pricePaise' => $pricePaise,
            'size' => trim($validated['size']),
            'color' => trim($validated['color']),
        ];
    }

    /** @return list<array{id: string, name: string, types: list<array{id: string, name: string}>}> */
    private function catalogForForm(): array
    {
        return DesignCategory::query()
            ->where('is_active', true)
            ->with(['types' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (DesignCategory $category) => [
                'id' => $category->public_id,
                'name' => $category->name,
                'types' => $category->types->map(fn ($type) => [
                    'id' => $type->public_id,
                    'name' => $type->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
