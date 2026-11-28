<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiImageEmbeddingService
{
    public function __construct(
        private readonly GoogleCloudAccessTokenService $accessTokens,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('designhub.image_matching.gemini.enabled')
            && filled(config('designhub.image_matching.gemini.project_id'))
            && filled(config('designhub.image_matching.gemini.credentials'));
    }

    /** @return list<float> */
    public function embedImage(string $contents, string $mime): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Gemini image embeddings are disabled.');
        }

        $supported = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/bmp',
            'image/heic',
            'image/heif',
            'image/avif',
        ];

        $mime = strtolower($mime);

        if (! in_array($mime, $supported, true)) {
            throw new RuntimeException("Gemini image embeddings do not support {$mime} images.");
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($this->accessTokens->token())
            ->withHeaders(['x-goog-user-project' => $this->projectId()])
            ->timeout((int) config('designhub.image_matching.timeout_seconds', 30))
            ->post($this->endpoint(), [
                'content' => [
                    'parts' => [[
                        'inlineData' => [
                            'mimeType' => $mime,
                            'data' => base64_encode($contents),
                        ],
                    ]],
                ],
                'embedContentConfig' => [
                    'outputDimensionality' => (int) config('designhub.image_matching.dimensions', 512),
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini image embedding failed: '.$response->body());
        }

        $values = $response->json('embedding.values') ?? $response->json('embeddings.0.values');

        if (! is_array($values) || $values === []) {
            throw new RuntimeException('Gemini did not return an image embedding.');
        }

        $vector = array_map(static fn ($value): float => (float) $value, array_values($values));
        $expectedDimensions = (int) config('designhub.image_matching.dimensions', 512);

        if (count($vector) !== $expectedDimensions) {
            throw new RuntimeException(sprintf(
                'Gemini returned %d dimensions; %d were expected.',
                count($vector),
                $expectedDimensions,
            ));
        }

        return $vector;
    }

    private function endpoint(): string
    {
        $location = (string) config('designhub.image_matching.gemini.location', 'global');
        $model = rawurlencode((string) config('designhub.image_matching.gemini.model', 'gemini-embedding-2'));
        $base = $location === 'global'
            ? 'https://aiplatform.googleapis.com'
            : 'https://aiplatform.'.rawurlencode($location).'.rep.googleapis.com';

        return sprintf(
            '%s/v1/projects/%s/locations/%s/publishers/google/models/%s:embedContent',
            $base,
            rawurlencode($this->projectId()),
            rawurlencode($location),
            $model,
        );
    }

    private function projectId(): string
    {
        return (string) config('designhub.image_matching.gemini.project_id');
    }
}
