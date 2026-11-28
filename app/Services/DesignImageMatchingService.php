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
        private readonly GeminiImageEmbeddingService $embeddings,
    ) {}

    /** @param list<array{path: string}> $images */
    public function assertStoredImagesAreUnique(array $images, ?Design $ignore = null): void
    {
        $seen = [];

        foreach ($images as $image) {
            $fingerprint = $this->fingerprints->fromPublicPath($image['path']);

            if (isset($seen[$fingerprint['sha256']])) {
                throw new DuplicateDesignImageException(100);
            }

            $seen[$fingerprint['sha256']] = true;
            $this->assertFingerprintIsUnique($fingerprint, $ignore);
        }
    }

    /** @param iterable<UploadedFile> $images */
    public function assertUploadedImagesAreUnique(iterable $images, ?Design $ignore = null): void
    {
        $seen = [];

        foreach ($images as $image) {
            $fingerprint = $this->fingerprints->fromUploadedFile($image);

            if (isset($seen[$fingerprint['sha256']])) {
                throw new DuplicateDesignImageException(100);
            }

            $seen[$fingerprint['sha256']] = true;
            $this->assertFingerprintIsUnique($fingerprint, $ignore);
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

        if ($this->aiSearchEnabled()) {
            try {
                $vector = $this->embeddings->embedImage($contents, $mime);

                return [
                    'engine' => 'gemini_embedding_2',
                    'matches' => $this->uniqueDesignMatches(
                        $this->databaseEmbeddingMatches($vector, $filters, $minimum, $limit),
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
        $design->imageFingerprints()->delete();

        foreach ($this->designImages($design) as $index => $image) {
            try {
                $fingerprint = $this->fingerprints->fromPublicPath($image['path']);
                $record = $design->imageFingerprints()->create([
                    'image_index' => $index,
                    'image_path' => $image['path'],
                    'sha256' => $fingerprint['sha256'],
                    'perceptual_hash' => $fingerprint['perceptual_hash'],
                    'color_histogram' => $fingerprint['color_histogram'],
                    'grayscale_layout' => $fingerprint['grayscale_layout'],
                    'embedding_model' => $this->aiSearchEnabled()
                        ? config('designhub.image_matching.gemini.model')
                        : null,
                    'embedding_status' => $this->aiSearchEnabled() ? 'pending' : 'disabled',
                ]);

                if ($this->aiSearchEnabled()) {
                    try {
                        $record->update([
                            'embedding' => $this->embeddings->embedImage($fingerprint['contents'], $fingerprint['mime']),
                            'embedding_status' => 'ready',
                            'embedding_error' => null,
                        ]);
                    } catch (Throwable $exception) {
                        $record->update([
                            'embedding_status' => 'failed',
                            'embedding_error' => Str::limit($exception->getMessage(), 1000),
                        ]);

                        Log::error('Unable to generate a Gemini image embedding.', [
                            'design' => $design->public_id,
                            'image' => $image['path'],
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            } catch (Throwable $exception) {
                Log::warning('Unable to fingerprint a design image.', [
                    'design' => $design->public_id,
                    'image' => $image['path'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

    }

    public function removeDesign(Design $design): void
    {
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

        if ($this->aiSearchEnabled()) {
            try {
                $vector = $this->embeddings->embedImage($fingerprint['contents'], $fingerprint['mime']);
                $match = collect($this->databaseEmbeddingMatches(
                    $vector,
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

    private function aiSearchEnabled(): bool
    {
        return $this->embeddings->enabled();
    }

    /**
     * @param  list<float>  $queryEmbedding
     * @param  array<string, string|null>  $filters
     * @return list<array{designId: string, score: float}>
     */
    private function databaseEmbeddingMatches(
        array $queryEmbedding,
        array $filters,
        float $minimum,
        int $limit,
        ?string $excludeDesignId = null,
    ): array {
        $matches = collect();

        DesignImageFingerprint::query()
            ->with('design:id,public_id')
            ->where('embedding_status', 'ready')
            ->whereNotNull('embedding')
            ->whereHas('design', function ($designQuery) use ($filters, $excludeDesignId) {
                $designQuery->listed()
                    ->when($excludeDesignId, fn ($query, $id) => $query->where('public_id', '!=', $id))
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
            ->chunkById(200, function (Collection $records) use ($queryEmbedding, $minimum, $matches) {
                foreach ($records as $record) {
                    if (! is_array($record->embedding) || $record->design === null) {
                        continue;
                    }

                    $score = round($this->cosineSimilarity($queryEmbedding, $record->embedding) * 100, 2);

                    if ($score >= $minimum) {
                        $matches->push([
                            'designId' => $record->design->public_id,
                            'score' => $score,
                        ]);
                    }
                }
            });

        return $this->uniqueDesignMatches($matches->all(), $limit);
    }

    /** @param list<float|int|string> $left @param list<float|int|string> $right */
    private function cosineSimilarity(array $left, array $right): float
    {
        if ($left === [] || count($left) !== count($right)) {
            return 0.0;
        }

        $dot = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;

        foreach ($left as $index => $value) {
            $leftValue = (float) $value;
            $rightValue = (float) $right[$index];
            $dot += $leftValue * $rightValue;
            $leftMagnitude += $leftValue ** 2;
            $rightMagnitude += $rightValue ** 2;
        }

        if ($leftMagnitude <= 0.0 || $rightMagnitude <= 0.0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $dot / (sqrt($leftMagnitude) * sqrt($rightMagnitude))));
    }
}
