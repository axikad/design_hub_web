<?php

namespace App\Services;

use App\Models\Design;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleProductSearchIndex
{
    public function __construct(
        private readonly GoogleCloudAccessTokenService $tokens,
    ) {}

    public function enabled(): bool
    {
        $config = config('designhub.image_matching.google', []);

        return (bool) ($config['enabled'] ?? false)
            && filled($config['project_id'] ?? null)
            && filled($config['location'] ?? null)
            && filled($config['bucket'] ?? null)
            && filled($config['product_set_id'] ?? null)
            && filled($config['product_category'] ?? null)
            && filled($config['credentials'] ?? null)
            && is_file((string) $config['credentials']);
    }

    public function ensureProductSet(): void
    {
        $response = $this->client()->post(
            $this->visionEndpoint('/productSets').'?productSetId='.rawurlencode($this->productSetId()),
            ['displayName' => 'DesignHub marketplace designs'],
        );

        if (! $response->successful() && $response->status() !== 409) {
            throw new RuntimeException('Unable to create the Google Product Set: '.$response->body());
        }
    }

    /**
     * @param  list<array{contents: string, mime: string, extension: string, index: int}>  $images
     * @return list<array{index: int, productId: string, referenceImageId: string, gcsUri: string}>
     */
    public function upsertDesign(Design $design, array $images): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $this->ensureProductSet();
        $productId = $this->productId($design);
        $this->deleteProduct($productId, ignoreMissing: true);
        $this->deleteObjectsWithPrefix('designs/'.$design->public_id.'/');

        $labels = collect([
            'design_id' => $design->public_id,
            'machine_id' => $design->machine?->public_id ?? $design->machineType?->machine?->public_id,
            'machine_type_id' => $design->machine_type_id,
            'category_id' => $design->category_id,
        ])->filter(fn ($value) => filled($value))
            ->map(fn ($value, $key) => ['key' => $key, 'value' => (string) $value])
            ->values()
            ->all();

        $response = $this->client()->post(
            $this->visionEndpoint('/products').'?productId='.rawurlencode($productId),
            [
                'displayName' => Str::limit($design->title ?: $design->public_id, 4096, ''),
                'description' => 'DesignHub marketplace design '.$design->public_id,
                'productCategory' => $this->category(),
                'productLabels' => $labels,
            ],
        );

        $this->ensureSuccessful($response, 'create Google product');

        $response = $this->client()->post(
            $this->visionEndpoint('/productSets/'.rawurlencode($this->productSetId()).':addProduct'),
            ['product' => $this->productName($productId)],
        );
        $this->ensureSuccessful($response, 'add product to Google Product Set');

        $indexed = [];

        foreach ($images as $image) {
            $hash = substr(hash('sha256', $image['contents']), 0, 16);
            $objectName = "designs/{$design->public_id}/{$image['index']}-{$hash}.{$image['extension']}";
            $gcsUri = $this->uploadObject($objectName, $image['contents'], $image['mime']);
            $referenceImageId = 'image-'.$image['index'].'-'.$hash;
            $response = $this->client()->post(
                $this->visionEndpoint('/products/'.rawurlencode($productId).'/referenceImages')
                    .'?referenceImageId='.rawurlencode($referenceImageId),
                ['uri' => $gcsUri],
            );
            $this->ensureSuccessful($response, 'create Google reference image');

            $indexed[] = [
                'index' => $image['index'],
                'productId' => $productId,
                'referenceImageId' => $referenceImageId,
                'gcsUri' => $gcsUri,
            ];
        }

        return $indexed;
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return list<array{designId: string, score: float}>
     */
    public function search(string $contents, array $filters, float $minimumScore, int $limit): array
    {
        $filter = collect([
            'machineId' => 'machine_id',
            'machineTypeId' => 'machine_type_id',
            'categoryId' => 'category_id',
        ])->map(fn ($label, $input) => filled($filters[$input] ?? null)
            ? $label.' = '.$this->filterValue((string) $filters[$input])
            : null)
            ->filter()
            ->implode(' AND ');

        $response = $this->client()->post('https://vision.googleapis.com/v1/images:annotate', [
            'requests' => [[
                'image' => ['content' => base64_encode($contents)],
                'features' => [[
                    'type' => 'PRODUCT_SEARCH',
                    'maxResults' => max(1, min(100, $limit)),
                ]],
                'imageContext' => [
                    'productSearchParams' => array_filter([
                        'productSet' => $this->productSetName(),
                        'productCategories' => [$this->category()],
                        'filter' => $filter ?: null,
                    ]),
                ],
            ]],
        ]);
        $this->ensureSuccessful($response, 'search Google Product Set');

        $apiError = $response->json('responses.0.error.message');

        if (is_string($apiError) && $apiError !== '') {
            throw new RuntimeException('Google Product Search failed: '.$apiError);
        }

        return collect($response->json('responses.0.productSearchResults.results', []))
            ->map(function (array $result): ?array {
                $name = $result['product']['name'] ?? null;

                if (! is_string($name) || $name === '') {
                    return null;
                }

                return [
                    'designId' => (string) Str::afterLast($name, '/'),
                    'score' => round(max(0, min(1, (float) ($result['score'] ?? 0))) * 100, 2),
                ];
            })
            ->filter(fn (?array $match) => $match !== null && $match['score'] >= $minimumScore)
            ->take($limit)
            ->values()
            ->all();
    }

    public function removeDesign(Design $design): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->deleteProduct($this->productId($design), ignoreMissing: true);
        $this->deleteObjectsWithPrefix('designs/'.$design->public_id.'/');
    }

    private function uploadObject(string $name, string $contents, string $mime): string
    {
        $endpoint = 'https://storage.googleapis.com/upload/storage/v1/b/'
            .rawurlencode($this->bucket()).'/o?uploadType=media&name='.rawurlencode($name);
        $response = Http::withToken($this->tokens->token())
            ->withHeaders([
                'Content-Type' => $mime,
                'x-goog-user-project' => $this->projectId(),
            ])
            ->timeout($this->timeout())
            ->send('POST', $endpoint, ['body' => $contents]);
        $this->ensureSuccessful($response, 'upload Google Cloud Storage object');

        return 'gs://'.$this->bucket().'/'.$name;
    }

    private function deleteProduct(string $productId, bool $ignoreMissing = false): void
    {
        $response = $this->client()->delete($this->visionEndpoint('/products/'.rawurlencode($productId)));

        if ($ignoreMissing && $response->status() === 404) {
            return;
        }

        $this->ensureSuccessful($response, 'delete Google product');
    }

    private function deleteObjectsWithPrefix(string $prefix): void
    {
        $response = $this->client()->get(
            'https://storage.googleapis.com/storage/v1/b/'.rawurlencode($this->bucket()).'/o',
            ['prefix' => $prefix],
        );
        $this->ensureSuccessful($response, 'list Google Cloud Storage objects');

        foreach ($response->json('items', []) as $object) {
            if (filled($object['name'] ?? null)) {
                $delete = $this->client()->delete(
                    'https://storage.googleapis.com/storage/v1/b/'.rawurlencode($this->bucket())
                    .'/o/'.rawurlencode((string) $object['name']),
                );
                $this->ensureSuccessful($delete, 'delete Google Cloud Storage object');
            }
        }
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->tokens->token())
            ->withHeaders(['x-goog-user-project' => $this->projectId()])
            ->timeout($this->timeout());
    }

    private function ensureSuccessful(Response $response, string $action): void
    {
        if (! $response->successful()) {
            throw new RuntimeException("Unable to {$action}: ".$response->body());
        }
    }

    private function visionEndpoint(string $suffix): string
    {
        return 'https://vision.googleapis.com/v1/projects/'.rawurlencode($this->projectId())
            .'/locations/'.rawurlencode($this->location()).$suffix;
    }

    private function productId(Design $design): string
    {
        return Str::limit((string) $design->public_id, 128, '');
    }

    private function productName(string $productId): string
    {
        return 'projects/'.$this->projectId().'/locations/'.$this->location().'/products/'.$productId;
    }

    private function productSetName(): string
    {
        return 'projects/'.$this->projectId().'/locations/'.$this->location().'/productSets/'.$this->productSetId();
    }

    private function filterValue(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_.-]/', '', $value) ?: 'unknown';
    }

    private function projectId(): string { return (string) config('designhub.image_matching.google.project_id'); }
    private function location(): string { return (string) config('designhub.image_matching.google.location'); }
    private function bucket(): string { return (string) config('designhub.image_matching.google.bucket'); }
    private function productSetId(): string { return (string) config('designhub.image_matching.google.product_set_id'); }
    private function category(): string { return (string) config('designhub.image_matching.google.product_category'); }
    private function timeout(): int { return (int) config('designhub.image_matching.timeout_seconds', 30); }
}
