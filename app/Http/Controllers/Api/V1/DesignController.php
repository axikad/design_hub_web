<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Models\Design;
use App\Models\DesignFileType;
use App\Models\DesignType;
use App\Services\PurchaseAccessService;
use App\Services\UploadService;
use App\Support\ApiResponse;
use App\Support\DesignTypeResolver;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DesignController extends Controller
{
    public function __construct(
        private readonly UploadService $uploads,
        private readonly PurchaseAccessService $access,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'section' => ['sometimes', 'string', Rule::in(['latest', 'today', 'tomorrow'])],
            'categoryId' => ['sometimes', 'string', 'max:120'],
            'machineTypeId' => ['sometimes', 'string', 'max:120'],
            'search' => ['sometimes', 'string', 'max:120'],
        ]);
        $today = now(config('designhub.marketplace_timezone'))->toDateString();
        $tomorrow = now(config('designhub.marketplace_timezone'))->addDay()->toDateString();

        $designs = Design::query()
            ->listed()
            ->when(($filters['section'] ?? null) === 'today', fn ($query) => $query->scheduledFor($today))
            ->when(($filters['section'] ?? null) === 'tomorrow', fn ($query) => $query->scheduledFor($tomorrow))
            ->when(! in_array(($filters['section'] ?? null), ['today', 'tomorrow'], true), fn ($query) => $query->published($today))
            ->when($filters['categoryId'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['machineTypeId'] ?? null, fn ($query, $machineTypeId) => $query->where('machine_type_id', $machineTypeId))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', '%'.$search.'%')
                        ->orWhere('file_name', 'like', '%'.$search.'%');
                });
            })
            ->with(['owner', 'type.category', 'fileType', 'variants.type'])
            ->orderByDesc('publish_date')
            ->latest('created_at')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated(
            $designs,
            $designs->getCollection()->map(
                fn (Design $design) => (new DesignResource($design))->resolve($request)
            )->values(),
            'Designs retrieved successfully.',
        );
    }

    public function show(Request $request, string $designId)
    {
        $design = Design::query()->listed()->with('owner')->where('public_id', $designId)->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        return ApiResponse::data(
            (new DesignResource($design))->resolve($request),
            message: 'Design retrieved successfully.',
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validatedDesignPayload($request);

        $design = DB::transaction(function () use ($request, $validated) {
            $design = Design::query()->create([
                'user_id' => $request->user()->id,
                'design_type_id' => $validated['design_type_id'],
                'design_file_type_id' => $validated['design_file_type_id'],
                'machine_type_id' => $validated['machineTypeId'],
                'category_id' => $validated['categoryId'],
                'show_price' => $validated['showPrice'],
                'publish_date' => $validated['publishDate'],
                'details' => $validated['details'],
                'images' => $validated['images'],
                'files' => $validated['files'],
                'title' => $validated['title'],
                'price_paise' => $validated['pricePaise'],
                'size' => $validated['size'],
                'color' => $validated['color'],
                'preview_path' => $validated['preview_path'],
                'stitch_path' => $validated['stitch_path'],
                'file_name' => $validated['fileName'],
            ]);

            $design->variants()->createMany($validated['variants']);

            return $design;
        });

        $design->load(['owner', 'type.category', 'fileType', 'variants.type']);

        return ApiResponse::data(
            (new DesignResource($design))->resolve($request),
            201,
            'Design created successfully.',
        );
    }

    public function update(Request $request, string $designId)
    {
        $design = Design::query()->listed()->where('public_id', $designId)->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        if ($design->user_id !== $request->user()->id) {
            return ApiResponse::error('You cannot edit this design.', 'design_update_forbidden', 403);
        }

        $validated = $this->validatedDesignPayload($request, $design);

        DB::transaction(function () use ($design, $validated) {
            $design->update([
                'design_type_id' => $validated['design_type_id'],
                'design_file_type_id' => $validated['design_file_type_id'],
                'machine_type_id' => $validated['machineTypeId'],
                'category_id' => $validated['categoryId'],
                'show_price' => $validated['showPrice'],
                'publish_date' => $validated['publishDate'],
                'details' => $validated['details'],
                'images' => $validated['images'],
                'files' => $validated['files'],
                'title' => $validated['title'],
                'price_paise' => $validated['pricePaise'],
                'size' => $validated['size'],
                'color' => $validated['color'],
                'preview_path' => $validated['preview_path'],
                'stitch_path' => $validated['stitch_path'],
                'file_name' => $validated['fileName'],
            ]);

            $design->variants()->delete();
            $design->variants()->createMany($validated['variants']);
        });

        $design->load(['owner', 'type.category', 'fileType', 'variants.type']);

        return ApiResponse::data(
            (new DesignResource($design))->resolve($request),
            message: 'Design updated successfully.',
        );
    }

    public function destroy(Request $request, string $designId)
    {
        $design = Design::query()->listed()->where('public_id', $designId)->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        if ($design->user_id !== $request->user()->id) {
            return ApiResponse::error('You cannot delete this design.', 'design_delete_forbidden', 403);
        }

        $design->update(['deleted_at' => now()]);

        return ApiResponse::success('Design deleted successfully.');
    }

    public function download(Request $request, string $designId)
    {
        $design = Design::query()->listed()->where('public_id', $designId)->first();

        if ($design === null) {
            return ApiResponse::error('Design not found.', 'design_not_found', 404);
        }

        if (! $this->access->canDownloadStitch($request->user(), $design)) {
            return ApiResponse::error(
                'Purchase this design to download the stitch file.',
                'design_download_forbidden',
                403,
            );
        }

        $fileIndex = max($request->integer('file', 0), 0);
        $file = $this->designFile($design, $fileIndex);

        if ($file === null) {
            return ApiResponse::error('Stitch file is unavailable.', 'stitch_file_not_found', 404);
        }

        $expiresAt = now()->addMinutes((int) config('designhub.download_url_ttl_minutes'));
        $url = URL::temporarySignedRoute(
            'api.v1.files.stitch',
            $expiresAt,
            ['design' => $design->public_id, 'file' => $fileIndex]
        );

        return ApiResponse::data([
            'url' => $url,
            'fileName' => $file['name'],
            'expiresAt' => $expiresAt->utc()->toIso8601String(),
        ], message: 'Download URL created successfully.');
    }

    public function streamStitch(Request $request, string $designId)
    {
        $design = Design::query()->listed()->where('public_id', $designId)->firstOrFail();

        $file = $this->designFile($design, max($request->integer('file', 0), 0));

        if ($file === null) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $file['path'],
            $file['name'],
        );
    }

    private function validatedDesignPayload(Request $request, ?Design $existingDesign = null): array
    {
        $validated = $request->validate([
            'machineTypeId' => ['sometimes', 'nullable', 'string', 'max:120'],
            'categoryId' => ['sometimes', 'nullable', 'string', 'max:120'],
            'showPrice' => ['sometimes', 'boolean'],
            'publishDate' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'title' => ['required_without:designTypes', 'nullable', 'string', 'min:1', 'max:120'],
            'pricePaise' => ['required', 'integer', 'min:100', 'max:100000000'],
            'size' => ['required_without:designTypes', 'nullable', 'string', 'max:120'],
            'color' => ['sometimes', 'nullable', 'string', 'max:120'],
            'imageUri' => ['required_without:images', 'nullable', 'string'],
            'fileName' => ['required_without:files', 'nullable', 'string', 'max:255'],
            'fileUri' => ['required_without:files', 'nullable', 'string'],
            'typeId' => ['required_without:designTypes', 'nullable', 'string', 'max:120'],
            'designTypes' => ['required_without:typeId', 'array', 'min:1', 'max:20'],
            'designTypes.*.typeId' => ['required', 'string', 'distinct', 'max:120'],
            'designTypes.*.details' => ['required', 'array'],
            'designTypes.*.details.nameOrNumber' => ['required', 'string', 'max:120'],
            'designTypes.*.details.stitchCount' => ['required', 'integer', 'min:0'],
            'designTypes.*.details.needle' => ['required', 'integer', 'min:0'],
            'designTypes.*.details.area' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'designTypes.*.details.height' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'designTypes.*.details.width' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'designFileTypeId' => [
                'required',
                'string',
                Rule::exists('design_file_types', 'public_id')->where('is_active', true),
            ],
            'details' => ['sometimes', 'array', 'min:1', 'max:50'],
            'details.*.names' => ['required_with:details', 'array', 'min:1', 'max:20'],
            'details.*.names.*' => ['required', 'string', 'max:120'],
            'details.*.stitch' => ['required_with:details', 'string', 'max:30'],
            'details.*.needle' => ['required_with:details', 'integer', 'min:0'],
            'details.*.area' => ['required_with:details', 'string', 'max:120'],
            'details.*.height' => ['required_with:details', 'string', 'max:30'],
            'details.*.width' => ['required_with:details', 'string', 'max:30'],
            'images' => ['sometimes', 'array', 'min:1', 'max:20'],
            'images.*.name' => ['required_with:images', 'string', 'max:255'],
            'images.*.uri' => ['required_with:images', 'string', 'max:2048'],
            'files' => ['sometimes', 'array', 'min:1', 'max:20'],
            'files.*.name' => ['required_with:files', 'string', 'max:255'],
            'files.*.uri' => ['required_with:files', 'string', 'max:2048'],
        ]);

        $selectedTypeId = $validated['designTypes'][0]['typeId'] ?? $validated['typeId'];

        try {
            $selection = DesignTypeResolver::resolveMobileSelection(
                $selectedTypeId,
                $validated['categoryId'] ?? null,
                $validated['machineTypeId'] ?? null,
            );
        } catch (\InvalidArgumentException $exception) {
            $field = isset($validated['designTypes']) ? 'designTypes.0.typeId' : 'typeId';

            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }

        $designFileType = DesignFileType::query()
            ->where('public_id', $validated['designFileTypeId'])
            ->where('is_active', true)
            ->firstOrFail();

        try {
            $images = $this->resolveAttachments(
                $request,
                $validated['images'] ?? [[
                    'name' => basename(parse_url($validated['imageUri'], PHP_URL_PATH) ?: 'preview.jpg'),
                    'uri' => $validated['imageUri'],
                ]],
                'photo',
                'images',
            );
            $files = $this->resolveAttachments(
                $request,
                $validated['files'] ?? [[
                    'name' => $validated['fileName'],
                    'uri' => $validated['fileUri'],
                ]],
                'stitch',
                'files',
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['imageUri' => $exception->getMessage()]);
        }

        if ($images === [] || $files === []) {
            throw ValidationException::withMessages(['imageUri' => 'Photo and stitch file are required.']);
        }

        if (isset($validated['designTypes'])) {
            [$variants, $details] = $this->resolveDesignTypeVariants(
                $validated['designTypes'],
                $selection['category_id'],
            );
        } else {
            [$variants, $details] = $this->resolveLegacyVariant(
                $validated,
                $selection['design_type_id'],
            );
        }

        $firstVariant = $variants[0] ?? [
            'name_or_number' => trim($validated['title']),
            'height' => 0,
            'width' => 0,
        ];
        $summarySize = isset($validated['designTypes'])
            ? $firstVariant['height'].' × '.$firstVariant['width']
            : (($details[0]['height'] ?? '') !== '' && ($details[0]['width'] ?? '') !== ''
                ? trim($details[0]['height']).' × '.trim($details[0]['width'])
                : trim($validated['size']));

        return [
            'design_type_id' => $selection['design_type_id'],
            'design_file_type_id' => $designFileType->id,
            'machineTypeId' => $selection['machine_type_id'],
            'categoryId' => $selection['category_id'],
            'showPrice' => $validated['showPrice'] ?? true,
            'publishDate' => $validated['publishDate']
                ?? $existingDesign?->publish_date?->format('Y-m-d')
                ?? now(config('designhub.marketplace_timezone'))->toDateString(),
            'variants' => $variants,
            'details' => $details,
            'images' => $images,
            'files' => $files,
            'title' => $firstVariant['name_or_number'],
            'pricePaise' => $validated['pricePaise'],
            'size' => $summarySize,
            'color' => trim($validated['color'] ?? ''),
            'preview_path' => $images[0]['path'],
            'stitch_path' => $files[0]['path'],
            'fileName' => $files[0]['name'],
        ];
    }

    private function resolveDesignTypeVariants(array $designTypes, string $categoryId): array
    {
        $types = DesignType::query()
            ->with('category')
            ->whereIn('public_id', collect($designTypes)->pluck('typeId'))
            ->where('is_active', true)
            ->get()
            ->keyBy('public_id');

        $variants = collect($designTypes)->map(function (array $item, int $index) use ($types, $categoryId) {
            $type = $types->get($item['typeId']);

            if ($type === null || $type->category === null || ! $type->category->is_active) {
                throw ValidationException::withMessages([
                    'designTypes.'.$index.'.typeId' => 'Selected type is invalid or inactive.',
                ]);
            }

            if ($type->category->public_id !== $categoryId) {
                throw ValidationException::withMessages([
                    'designTypes.'.$index.'.typeId' => 'The selected type does not belong to this category.',
                ]);
            }

            $details = $item['details'];

            return [
                'design_type_id' => $type->id,
                'name_or_number' => trim($details['nameOrNumber']),
                'stitch_count' => $details['stitchCount'],
                'needle' => $details['needle'],
                'area' => $details['area'],
                'height' => $details['height'],
                'width' => $details['width'],
                'sort_order' => $index,
            ];
        })->values()->all();

        return [$variants, $this->legacyDetailsFromVariants($variants)];
    }

    private function resolveLegacyVariant(array $validated, ?int $designTypeId): array
    {
        $details = $validated['details'] ?? [[
            'names' => [$validated['title']],
            'stitch' => '0',
            'needle' => 0,
            'area' => $validated['size'],
            'height' => '0',
            'width' => '0',
        ]];
        $detail = $details[0];
        $variant = [
            'design_type_id' => $designTypeId,
            'name_or_number' => trim($detail['names'][0] ?? $validated['title']),
            'stitch_count' => is_numeric($detail['stitch'] ?? null) ? (int) $detail['stitch'] : 0,
            'needle' => (int) ($detail['needle'] ?? 0),
            'area' => is_numeric($detail['area'] ?? null) ? $detail['area'] : 0,
            'height' => is_numeric($detail['height'] ?? null) ? $detail['height'] : 0,
            'width' => is_numeric($detail['width'] ?? null) ? $detail['width'] : 0,
            'sort_order' => 0,
        ];

        return [$designTypeId === null ? [] : [$variant], $details];
    }

    private function legacyDetailsFromVariants(array $variants): array
    {
        return collect($variants)->map(fn (array $variant) => [
            'names' => [$variant['name_or_number']],
            'stitch' => (string) $variant['stitch_count'],
            'needle' => $variant['needle'],
            'area' => (string) $variant['area'],
            'height' => (string) $variant['height'],
            'width' => (string) $variant['width'],
        ])->values()->all();
    }

    private function resolveAttachments(
        Request $request,
        array $attachments,
        string $kind,
        string $field,
    ): array {
        return collect($attachments)->map(function (array $attachment, int $index) use ($request, $kind, $field) {
            try {
                $path = $this->uploads->resolveStoragePath($request->user(), $attachment['uri'], $kind);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    $field.'.'.$index.'.uri' => $exception->getMessage(),
                ]);
            }

            return [
                'name' => trim($attachment['name']),
                'path' => $path,
            ];
        })->values()->all();
    }

    private function designFile(Design $design, int $index): ?array
    {
        $files = $design->files;
        if (is_array($files) && isset($files[$index]['path'])) {
            return [
                'name' => $files[$index]['name'] ?? 'design.dst',
                'path' => $files[$index]['path'],
            ];
        }

        if ($index === 0 && $design->stitch_path !== null) {
            return [
                'name' => $design->file_name ?? 'design.dst',
                'path' => $design->stitch_path,
            ];
        }

        return null;
    }
}
