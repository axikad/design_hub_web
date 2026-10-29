<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Models\Design;
use App\Models\SiteSetting;
use App\Services\DesignImageMatchingService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DesignImageSearchController extends Controller
{
    public function __construct(
        private readonly DesignImageMatchingService $imageMatching,
    ) {}

    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'machineId' => ['sometimes', 'nullable', 'string', Rule::exists('machines', 'public_id')->where('is_active', true)],
            'machineTypeId' => ['sometimes', 'nullable', 'string', Rule::exists('machine_types', 'public_id')->where('is_active', true)],
            'categoryId' => ['sometimes', 'nullable', 'string', Rule::exists('design_categories', 'public_id')->where('is_active', true)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $image = $request->file('image');
        $contents = file_get_contents($image->getRealPath());

        if ($contents === false) {
            return ApiResponse::error('The selected image could not be read.', 'image_read_failed', 422);
        }

        $search = $this->imageMatching->searchWithMeta(
            $contents,
            $image->getMimeType() ?: 'image/jpeg',
            [
                'machineId' => $validated['machineId'] ?? null,
                'machineTypeId' => $validated['machineTypeId'] ?? null,
                'categoryId' => $validated['categoryId'] ?? null,
            ],
            $validated['limit'] ?? 20,
        );
        $matches = collect($search['matches']);
        $designs = Design::query()
            ->listed()
            ->published()
            ->forUserMachines($request->user())
            ->with(['owner', 'type.category', 'fileType', 'variants.type'])
            ->whereIn('public_id', $matches->pluck('designId'))
            ->get()
            ->keyBy('public_id');

        $results = $matches->map(function (array $match) use ($designs, $request) {
            $design = $designs->get($match['designId']);

            if ($design === null) {
                return null;
            }

            return [
                ...(new DesignResource($design))->resolve($request),
                'similarityScore' => $match['score'],
                'similarityLabel' => match (true) {
                    $match['score'] > 97 => 'Very close',
                    $match['score'] > 80 => 'Similar',
                    default => 'Related',
                },
            ];
        })->filter()->values();

        return ApiResponse::data([
            'engine' => $search['engine'],
            'minimumSimilarity' => SiteSetting::current()->image_search_similarity,
            'results' => $results,
        ], message: 'Image search completed successfully.');
    }
}
