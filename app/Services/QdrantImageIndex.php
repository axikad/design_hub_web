<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class QdrantImageIndex
{
    private bool $collectionReady = false;

    public function enabled(): bool
    {
        return filled(config('designhub.image_matching.qdrant_url'))
            && filled(config('designhub.image_matching.qdrant_api_key'));
    }

    /**
     * @param  list<array{id: string, contents: string, payload: array<string, mixed>}>  $points
     */
    public function upsert(array $points): void
    {
        if (! $this->enabled() || $points === []) {
            return;
        }

        $this->ensureCollection();

        $response = $this->client()->put($this->endpoint('/points?wait=true'), [
            'points' => collect($points)->map(fn (array $point) => [
                'id' => $point['id'],
                'vector' => [
                    'image' => base64_encode($point['contents']),
                    'model' => config('designhub.image_matching.model'),
                ],
                'payload' => $point['payload'],
            ])->all(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Qdrant image indexing failed: '.$response->body());
        }
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return list<array{designId: string, score: float}>
     */
    public function search(
        string $contents,
        array $filters,
        float $minimumScore,
        int $limit,
        ?string $excludeDesignId = null,
    ): array {
        if (! $this->enabled()) {
            return [];
        }

        $this->ensureCollection();
        $must = [['key' => 'listed', 'match' => ['value' => true]]];

        foreach (['machineId' => 'machine_id', 'machineTypeId' => 'machine_type_id', 'categoryId' => 'category_id'] as $input => $payload) {
            if (filled($filters[$input] ?? null)) {
                $must[] = ['key' => $payload, 'match' => ['value' => $filters[$input]]];
            }
        }

        $filter = ['must' => $must];

        if ($excludeDesignId !== null) {
            $filter['must_not'] = [['key' => 'design_id', 'match' => ['value' => $excludeDesignId]]];
        }

        $response = $this->client()->post($this->endpoint('/points/query'), [
            'query' => [
                'image' => base64_encode($contents),
                'model' => config('designhub.image_matching.model'),
            ],
            'filter' => $filter,
            'score_threshold' => max(0, min(1, $minimumScore / 100)),
            'limit' => $limit,
            'with_payload' => true,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Qdrant image search failed: '.$response->body());
        }

        $points = $response->json('result.points') ?? $response->json('result') ?? [];

        return collect($points)
            ->filter(fn (array $point) => filled($point['payload']['design_id'] ?? null))
            ->map(fn (array $point) => [
                'designId' => (string) $point['payload']['design_id'],
                'score' => round(max(0, min(1, (float) ($point['score'] ?? 0))) * 100, 2),
            ])
            ->values()
            ->all();
    }

    /** @param list<string> $pointIds */
    public function delete(array $pointIds): void
    {
        if (! $this->enabled() || $pointIds === []) {
            return;
        }

        $response = $this->client()->post($this->endpoint('/points/delete?wait=true'), [
            'points' => array_values($pointIds),
        ]);

        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException('Qdrant image removal failed: '.$response->body());
        }
    }

    private function ensureCollection(): void
    {
        if ($this->collectionReady) {
            return;
        }

        $response = $this->client()->get($this->endpoint());

        if ($response->status() === 404) {
            $response = $this->client()->put($this->endpoint(), [
                'vectors' => [
                    'size' => (int) config('designhub.image_matching.dimensions', 512),
                    'distance' => 'Cosine',
                ],
            ]);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Qdrant collection is unavailable: '.$response->body());
        }

        $this->collectionReady = true;
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withHeaders(['api-key' => config('designhub.image_matching.qdrant_api_key')])
            ->timeout((int) config('designhub.image_matching.timeout_seconds', 30));
    }

    private function endpoint(string $suffix = ''): string
    {
        $base = rtrim((string) config('designhub.image_matching.qdrant_url'), '/');
        $collection = rawurlencode((string) config('designhub.image_matching.collection', 'design_images'));

        return "{$base}/collections/{$collection}{$suffix}";
    }
}
