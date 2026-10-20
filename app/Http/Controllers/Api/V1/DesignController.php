<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Models\Design;
use App\Models\DesignFileType;
use App\Services\PurchaseAccessService;
use App\Services\UploadService;
use App\Support\ApiResponse;
use App\Support\DesignTypeResolver;
use App\Support\Pagination;
use Illuminate\Http\Request;
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
        $designs = Design::query()
            ->listed()
            ->with(['owner', 'type.category', 'fileType'])
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

        $design = Design::query()->create([
            'user_id' => $request->user()->id,
            'design_type_id' => $validated['design_type_id'],
            'design_file_type_id' => $validated['design_file_type_id'],
            'machine_type_id' => $validated['machineTypeId'],
            'category_id' => $validated['categoryId'],
            'show_price' => $validated['showPrice'],
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

        $design->load(['owner', 'type.category', 'fileType']);

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

        $validated = $this->validatedDesignPayload($request);

        $design->update([
            'design_type_id' => $validated['design_type_id'],
            'design_file_type_id' => $validated['design_file_type_id'],
            'machine_type_id' => $validated['machineTypeId'],
            'category_id' => $validated['categoryId'],
            'show_price' => $validated['showPrice'],
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

        $design->load(['owner', 'type.category', 'fileType']);

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

    private function validatedDesignPayload(Request $request): array
    {
        $validated = $request->validate([
            'machineTypeId' => ['sometimes', 'nullable', 'string', 'max:120'],
            'categoryId' => ['sometimes', 'nullable', 'string', 'max:120'],
            'showPrice' => ['sometimes', 'boolean'],
            'title' => ['required', 'string', 'min:1', 'max:40'],
            'pricePaise' => ['required', 'integer', 'min:100', 'max:100000000'],
            'size' => ['required', 'string', 'max:120'],
            'color' => ['required', 'string', 'max:120'],
            'imageUri' => ['required', 'string'],
            'fileName' => ['required', 'string', 'max:255'],
            'fileUri' => ['required', 'string'],
            'typeId' => ['required', 'string', 'max:120'],
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

        try {
            $selection = DesignTypeResolver::resolveMobileSelection(
                $validated['typeId'],
                $validated['categoryId'] ?? null,
                $validated['machineTypeId'] ?? null,
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['typeId' => $exception->getMessage()]);
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

        $details = $validated['details'] ?? [[
            'names' => [$validated['title']],
            'stitch' => '',
            'needle' => 0,
            'area' => $validated['size'],
            'height' => '',
            'width' => '',
        ]];
        $firstDetail = $details[0];

        return [
            'design_type_id' => $selection['design_type_id'],
            'design_file_type_id' => $designFileType->id,
            'machineTypeId' => $selection['machine_type_id'],
            'categoryId' => $selection['category_id'],
            'showPrice' => $validated['showPrice'] ?? true,
            'details' => $details,
            'images' => $images,
            'files' => $files,
            'title' => trim($firstDetail['names'][0] ?? $validated['title']),
            'pricePaise' => $validated['pricePaise'],
            'size' => $firstDetail['height'] !== '' && $firstDetail['width'] !== ''
                ? trim($firstDetail['height']).' × '.trim($firstDetail['width'])
                : trim($validated['size']),
            'color' => trim($validated['color']),
            'preview_path' => $images[0]['path'],
            'stitch_path' => $files[0]['path'],
            'fileName' => $files[0]['name'],
        ];
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
