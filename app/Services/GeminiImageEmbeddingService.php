<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiImageEmbeddingService
{
    public function __construct(
        private readonly GoogleCloudAccessTokenService $accessTokens,
        private readonly GeminiImagePreprocessor $preprocessor,
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

        $prepared = $this->preprocessor->prepare($contents, $mime);
        $contents = $prepared['contents'];
        $mime = $prepared['mime'];

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

        $attempts = max(1, (int) config('designhub.image_matching.retry.attempts', 3));
        $baseDelay = max(0, (int) config('designhub.image_matching.retry.base_delay_ms', 1000));
        $response = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
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
            } catch (ConnectionException $exception) {
                if ($attempt === $attempts) {
                    throw new RuntimeException('Gemini image embedding connection failed.', previous: $exception);
                }

                $this->waitBeforeRetry($baseDelay, $attempt);

                continue;
            }

            if ($response->successful() || ! $this->shouldRetry($response->status(), $response->body()) || $attempt === $attempts) {
                break;
            }

            $this->waitBeforeRetry($baseDelay, $attempt);
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Gemini image embedding failed (HTTP %d): %s',
                $response->status(),
                $response->body(),
            ));
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

    private function shouldRetry(int $status, string $body): bool
    {
        return in_array($status, [408, 425, 429], true)
            || $status >= 500
            || str_contains(strtolower($body), 'automated queries')
            || str_contains(strtolower($body), "we're sorry");
    }

    private function waitBeforeRetry(int $baseDelay, int $attempt): void
    {
        if ($baseDelay > 0) {
            usleep($baseDelay * (2 ** ($attempt - 1)) * 1000);
        }
    }
}
