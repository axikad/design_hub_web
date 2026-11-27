<?php

namespace App\Services;

use App\Exceptions\DuplicateDesignImageException;
use App\Exceptions\ImageMatchingUnavailableException;
use App\Models\Design;
use App\Models\DesignImageFingerprint;
use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DesignImageMatchingService
{
    public function __construct(
        private readonly ImageFingerprintService $fingerprints,
        private readonly QdrantImageIndex $vectors,
        private readonly GoogleProductSearchIndex $google,
    ) {}

    /** @param list<array{path: string}> $images */
    public function assertStoredImagesAreUnique(array $images, ?Design $ignore = null): void
    {
        foreach ($images as $image) {
            $this->assertFingerprintIsUnique(
                $this->fingerprints->fromPublicPath($image['path']),
                $ignore,
            );
        }
    }

    /** @param iterable<UploadedFile> $images */
    public function assertUploadedImagesAreUnique(iterable $images, ?Design $ignore = null): void
    {
        foreach ($images as $image) {
            $this->assertFingerprintIsUnique(
                $this->fingerprints->fromUploadedFile($image),
                $ignore,
            );
        }
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return list<array{designId: string, score: float}>
     */
    public function search(string $contents, string $mime, array $filters = [], int $limit = 200): array
    {
        return $this->searchWithMeta($contents, $mime, $filters, $limit)['matches'];
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array{engine: string, matches: list<array{designId: string, score: float}>}
     */
    public function searchWithMeta(string $contents, string $mime, array $filters = [], int $limit = 200): array
    {
        $fingerprint = $this->fingerprints->fromContents($contents, $mime);
        $minimum = (float) SiteSetting::current()->image_search_similarity;

        if ($this->google->enabled()) {
            try {
                $matches = $this->uniqueDesignMatches(
                    $this->google->search($contents, $filters, $minimum, $limit),
                    $limit,
                );

                if ($matches !== []) {
                    return [
                        'engine' => 'google_product_search',
                        'matches' => $matches,
                    ];
                }

                Log::info('Google Product Search returned no matches; trying the configured fallback.');
            } catch (Throwable $exception) {
                Log::warning('Google Product Search failed; trying the configured fallback.', [
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($this->vectors->enabled()) {
            try {
                return [
                    'engine' => 'ai_embedding',
                    'matches' => $this->uniqueDesignMatches(
                        $this->vectors->search($contents, $filters, $minimum, $limit),
                    ),
                ];
            } catch (Throwable $exception) {
                Log::warning('AI image search failed; using local visual matching.', [
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'engine' => 'local_visual',
            'matches' => $this->localPerceptualMatches($fingerprint, $filters, $minimum, $limit),
        ];
    }

    public function syncDesign(Design $design): void
    {
        $design->loadMissing(['machine', 'machineType.machine']);
        $oldPointIds = $design->imageFingerprints()->whereNotNull('vector_id')->pluck('vector_id')->all();

        try {
            $this->vectors->delete($oldPointIds);
        } catch (Throwable $exception) {
            Log::warning('Unable to remove previous design image vectors.', [
                'design' => $design->public_id,
                'error' => $exception->getMessage(),
            ]);
        }

        $design->imageFingerprints()->delete();
        $points = [];
        $googleImages = [];
        $recordsByIndex = [];

        foreach ($this->designImages($design) as $index => $image) {
            try {
                $fingerprint = $this->fingerprints->fromPublicPath($image['path']);
                $vectorId = (string) Str::uuid();
                $record = $design->imageFingerprints()->create([
                    'image_index' => $index,
                    'image_path' => $image['path'],
                    'sha256' => $fingerprint['sha256'],
                    'perceptual_hash' => $fingerprint['perceptual_hash'],
                    'color_histogram' => $fingerprint['color_histogram'],
                    'grayscale_layout' => $fingerprint['grayscale_layout'],
                    'vector_id' => $this->vectors->enabled() ? $vectorId : null,
                    'vector_status' => $this->vectors->enabled() ? 'pending' : 'disabled',
                    'google_status' => $this->google->enabled() ? 'pending' : 'disabled',
                ]);
                $recordsByIndex[$index] = $record;

                if ($this->google->enabled()) {
                    $googleImages[] = [
                        'contents' => $fingerprint['contents'],
                        'mime' => $fingerprint['mime'],
                        'extension' => $this->imageExtension($fingerprint['mime'], $image['name'] ?? $image['path']),
                        'index' => $index,
                    ];
                }

                if ($this->vectors->enabled()) {
                    $points[] = [
                        'id' => $vectorId,
                        'contents' => $fingerprint['contents'],
                        'payload' => [
                            'design_id' => $design->public_id,
                            'image_index' => $index,
                            'machine_id' => $design->machine?->public_id ?? $design->machineType?->machine?->public_id,
                            'machine_type_id' => $design->machine_type_id,
                            'category_id' => $design->category_id,
                            'listed' => $design->deleted_at === null,
                        ],
                        'record' => $record,
                    ];
                }
            } catch (Throwable $exception) {
                Log::warning('Unable to fingerprint a design image.', [
                    'design' => $design->public_id,
                    'image' => $image['path'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($points !== []) {
            try {
                $this->vectors->upsert(collect($points)->map(fn (array $point) => [
                    'id' => $point['id'],
                    'contents' => $point['contents'],
                    'payload' => $point['payload'],
                ])->all());

                DesignImageFingerprint::query()
                    ->whereIn('id', collect($points)->pluck('record.id'))
                    ->update(['vector_status' => 'ready', 'vector_error' => null]);
            } catch (Throwable $exception) {
                DesignImageFingerprint::query()
                    ->whereIn('id', collect($points)->pluck('record.id'))
                    ->update([
                        'vector_status' => 'failed',
                        'vector_error' => Str::limit($exception->getMessage(), 1000),
                    ]);

                Log::error('Unable to index design images in Qdrant.', [
                    'design' => $design->public_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($googleImages !== []) {
            try {
                foreach ($this->google->upsertDesign($design, $googleImages) as $indexedImage) {
                    $record = $recordsByIndex[$indexedImage['index']] ?? null;

                    $record?->update([
                        'google_product_id' => $indexedImage['productId'],
                        'google_reference_image_id' => $indexedImage['referenceImageId'],
                        'google_gcs_uri' => $indexedImage['gcsUri'],
                        'google_status' => 'ready',
                        'google_error' => null,
                    ]);
                }
            } catch (Throwable $exception) {
                DesignImageFingerprint::query()
                    ->whereIn('id', collect($recordsByIndex)->pluck('id'))
                    ->update([
                        'google_status' => 'failed',
                        'google_error' => Str::limit($exception->getMessage(), 1000),
                    ]);

                Log::error('Unable to index design images in Google Product Search.', [
                    'design' => $design->public_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function removeDesign(Design $design): void
    {
        $pointIds = $design->imageFingerprints()->whereNotNull('vector_id')->pluck('vector_id')->all();

        try {
            $this->vectors->delete($pointIds);
        } catch (Throwable $exception) {
            Log::warning('Unable to remove design image vectors.', [
                'design' => $design->public_id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $this->google->removeDesign($design);
        } catch (Throwable $exception) {
            Log::warning('Unable to remove design images from Google Product Search.', [
                'design' => $design->public_id,
                'error' => $exception->getMessage(),
            ]);
        }

        $design->imageFingerprints()->delete();
    }

    /** @param array{sha256: string, perceptual_hash: string, contents: string, mime: string} $fingerprint */
    private function assertFingerprintIsUnique(array $fingerprint, ?Design $ignore): void
    {
        $exactMatch = DesignImageFingerprint::query()
            ->where('sha256', $fingerprint['sha256'])
            ->whereHas('design', fn ($query) => $query->listed())
            ->when($ignore, fn ($query) => $query->where('design_id', '!=', $ignore->id))
            ->with('design:id,public_id')
            ->first();

        if ($exactMatch !== null) {
            throw new DuplicateDesignImageException(100, $exactMatch->design?->public_id);
        }

        $minimum = (float) SiteSetting::current()->duplicate_image_similarity;

        if ($this->vectors->enabled()) {
            try {
                $match = collect($this->vectors->search(
                    $fingerprint['contents'],
                    [],
                    $minimum,
                    1,
                    $ignore?->public_id,
                ))->first();
            } catch (Throwable $exception) {
                throw new ImageMatchingUnavailableException($exception);
            }

            if ($match !== null) {
                throw new DuplicateDesignImageException($match['score'], $match['designId']);
            }

            return;
        }

        $match = $this->localPerceptualMatches($fingerprint, [], $minimum, 1, $ignore?->id)[0] ?? null;

        if ($match !== null) {
            throw new DuplicateDesignImageException($match['score'], $match['designId']);
        }
    }

    /**
     * @param  array{sha256: string, perceptual_hash: string, color_histogram: list<float>, grayscale_layout: list<float>, contents: string, mime: string}  $fingerprint
     * @param  array<string, string|null>  $filters
     * @return list<array{designId: string, score: float}>
     */
    private function localPerceptualMatches(
        array $fingerprint,
        array $filters,
        float $minimum,
        int $limit,
        ?int $ignoreDesignId = null,
    ): array {
        $query = DesignImageFingerprint::query()
            ->with('design:id,public_id')
            ->whereNotNull('perceptual_hash')
            ->whereHas('design', function ($designQuery) use ($filters) {
                $designQuery->listed()
                    ->when($filters['categoryId'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
                    ->when($filters['machineTypeId'] ?? null, fn ($query, $id) => $query->where('machine_type_id', $id))
                    ->when($filters['machineId'] ?? null, fn ($query, $id) => $query->where(function ($machineFilter) use ($id) {
                        $machineFilter
                            ->whereHas('machine', fn ($machineQuery) => $machineQuery->where('public_id', $id))
                            ->orWhere(function ($legacyQuery) use ($id) {
                                $legacyQuery
                                    ->whereNull('machine_id')
                                    ->whereHas('machineType.machine', fn ($machineQuery) => $machineQuery->where('public_id', $id));
                            });
                    }));
            })
            ->when($ignoreDesignId, fn ($builder) => $builder->where('design_id', '!=', $ignoreDesignId));

        $matches = collect();

        $query->chunkById(500, function (Collection $records) use ($fingerprint, $minimum, $matches) {
            foreach ($records as $record) {
                $hasLocalFeatures = is_array($record->color_histogram)
                    && is_array($record->grayscale_layout);

                $score = $hasLocalFeatures
                    ? $this->fingerprints->visualSimilarity(
                        $fingerprint['color_histogram'],
                        $fingerprint['grayscale_layout'],
                        $fingerprint['perceptual_hash'],
                        $record->color_histogram,
                        $record->grayscale_layout,
                        $record->perceptual_hash,
                    )['score']
                    : $this->fingerprints->similarity(
                        $fingerprint['perceptual_hash'],
                        $record->perceptual_hash,
                    );

                if ($score >= $minimum && $record->design !== null) {
                    $matches->push(['designId' => $record->design->public_id, 'score' => $score]);
                }
            }
        });

        return $this->uniqueDesignMatches($matches->all(), $limit);
    }

    /**
     * @param  list<array{designId: string, score: float}>  $matches
     * @return list<array{designId: string, score: float}>
     */
    private function uniqueDesignMatches(array $matches, ?int $limit = null): array
    {
        $matches = collect($matches)
            ->groupBy('designId')
            ->map(fn (Collection $designMatches) => $designMatches->sortByDesc('score')->first())
            ->sortByDesc('score')
            ->values();

        if ($limit !== null) {
            $matches = $matches->take($limit);
        }

        return $matches->all();
    }

    /** @return list<array{name: string, path: string}> */
    private function designImages(Design $design): array
    {
        if (is_array($design->images) && $design->images !== []) {
            return collect($design->images)
                ->filter(fn (array $image) => filled($image['path'] ?? null))
                ->values()
                ->all();
        }

        return $design->preview_path === null ? [] : [[
            'name' => basename($design->preview_path),
            'path' => $design->preview_path,
        ]];
    }

    private function imageExtension(string $mime, string $name): string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            default => strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'jpg',
        };
    }
}
