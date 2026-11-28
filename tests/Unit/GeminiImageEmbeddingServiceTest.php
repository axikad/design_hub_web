<?php

namespace Tests\Unit;

use App\Services\GeminiImageEmbeddingService;
use App\Services\GoogleCloudAccessTokenService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class GeminiImageEmbeddingServiceTest extends TestCase
{
    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'designhub-gemini-');
        file_put_contents($this->credentialsPath, '{}');

        config([
            'designhub.image_matching.dimensions' => 512,
            'designhub.image_matching.timeout_seconds' => 30,
            'designhub.image_matching.gemini' => [
                'enabled' => true,
                'project_id' => 'textile-design-hub',
                'location' => 'global',
                'model' => 'gemini-embedding-2',
                'credentials' => $this->credentialsPath,
            ],
        ]);

        $this->mock(GoogleCloudAccessTokenService::class, function (MockInterface $mock) {
            $mock->shouldReceive('token')->once()->andReturn('test-access-token');
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);

        parent::tearDown();
    }

    public function test_it_generates_a_512_dimension_image_embedding(): void
    {
        Http::fake([
            '*' => Http::response([
                'embedding' => ['values' => array_fill(0, 512, 0.125)],
            ]),
        ]);

        $vector = app(GeminiImageEmbeddingService::class)->embedImage('image-contents', 'image/png');

        $this->assertCount(512, $vector);
        $this->assertSame(0.125, $vector[0]);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://aiplatform.googleapis.com/v1/projects/textile-design-hub/locations/global/publishers/google/models/gemini-embedding-2:embedContent'
                && $request['content']['parts'][0]['inlineData']['mimeType'] === 'image/png'
                && $request['content']['parts'][0]['inlineData']['data'] === base64_encode('image-contents')
                && $request['embedContentConfig']['outputDimensionality'] === 512
                && $request->hasHeader('Authorization', 'Bearer test-access-token');
        });
    }
}
