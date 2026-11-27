<?php

namespace Tests\Unit;

use App\Services\GoogleCloudAccessTokenService;
use App\Services\GoogleProductSearchIndex;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class GoogleProductSearchIndexTest extends TestCase
{
    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'designhub-google-');
        file_put_contents($this->credentialsPath, '{}');

        config([
            'designhub.image_matching.google' => [
                'enabled' => true,
                'project_id' => 'textile-design-hub',
                'location' => 'asia-east1',
                'bucket' => 'designhub-test-bucket',
                'product_set_id' => 'designhub-designs',
                'product_category' => 'general-v1',
                'credentials' => $this->credentialsPath,
            ],
            'designhub.image_matching.timeout_seconds' => 30,
        ]);

        $this->mock(GoogleCloudAccessTokenService::class, function (MockInterface $mock) {
            $mock->shouldReceive('token')->andReturn('test-access-token');
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);

        parent::tearDown();
    }

    public function test_it_creates_the_configured_product_set_id_as_a_query_parameter(): void
    {
        Http::fake([
            '*' => Http::response(['name' => 'configured-product-set'], 200),
        ]);

        app(GoogleProductSearchIndex::class)->ensureProductSet();

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://vision.googleapis.com/v1/projects/textile-design-hub/locations/asia-east1/productSets?productSetId=designhub-designs'
                && $request['displayName'] === 'DesignHub marketplace designs'
                && $request->hasHeader('x-goog-user-project', 'textile-design-hub');
        });
    }

    public function test_it_searches_google_with_catalog_filters_and_maps_matches(): void
    {
        Http::fake([
            'https://vision.googleapis.com/v1/images:annotate' => Http::response([
                'responses' => [[
                    'productSearchResults' => [
                        'results' => [
                            [
                                'score' => 0.91,
                                'product' => [
                                    'name' => 'projects/textile-design-hub/locations/asia-east1/products/design_abc123',
                                ],
                            ],
                            [
                                'score' => 0.40,
                                'product' => [
                                    'name' => 'projects/textile-design-hub/locations/asia-east1/products/design_below_threshold',
                                ],
                            ],
                        ],
                    ],
                ]],
            ], 200),
        ]);

        $matches = app(GoogleProductSearchIndex::class)->search(
            'query-image-contents',
            [
                'machineId' => 'machine_123',
                'machineTypeId' => 'multi',
                'categoryId' => 'saree',
            ],
            70,
            20,
        );

        $this->assertSame([
            ['designId' => 'design_abc123', 'score' => 91.0],
        ], $matches);

        Http::assertSent(function (Request $request): bool {
            $params = $request->data()['requests'][0]['imageContext']['productSearchParams'] ?? [];

            return $request->url() === 'https://vision.googleapis.com/v1/images:annotate'
                && ($params['productSet'] ?? null) === 'projects/textile-design-hub/locations/asia-east1/productSets/designhub-designs'
                && ($params['productCategories'] ?? null) === ['general-v1']
                && ($params['filter'] ?? null) === 'machine_id = machine_123 AND machine_type_id = multi AND category_id = saree';
        });
    }
}
