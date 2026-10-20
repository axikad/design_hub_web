<?php

namespace Tests\Feature\Api;

use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\DesignFileType;
use App\Models\DesignType;
use App\Models\Role;
use App\Models\Upload;
use App\Models\User;
use App\Services\UploadService;
use App\Support\ApiResponse;
use Database\Seeders\DesignCatalogSeeder;
use Database\Seeders\DesignFileTypeSeeder;
use Database\Seeders\DesignFormOptionSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ApiResponseContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_success_responses_use_the_standard_envelope(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User retrieved successfully.')
            ->assertJsonPath('data.id', $user->public_id)
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonMissingPath('meta')
            ->assertJsonMissingPath('links')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_unauthenticated_responses_use_a_stable_error_code(): void
    {
        $this->getJson('/api/v1/users/me')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'code' => 'unauthenticated',
            ])
            ->assertJsonStructure(['success', 'message', 'code'])
            ->assertJsonMissingPath('errors')
            ->assertJsonMissingPath('data')
            ->assertJsonMissingPath('meta')
            ->assertJsonMissingPath('links');
    }

    public function test_validation_responses_contain_a_code_and_field_errors(): void
    {
        $this->postJson('/api/v1/auth/request-otp', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['flow', 'countryCode', 'mobileNumber']]);
    }

    public function test_explicit_static_otp_can_be_used_for_a_production_test_environment(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'app.env' => 'production',
            'designhub.otp_static_enabled' => true,
            'designhub.otp_static_code' => '1234',
            'designhub.otp_log_code' => false,
        ]);

        $payload = [
            'flow' => 'register',
            'name' => 'Rajvi',
            'countryCode' => '+91',
            'mobileNumber' => '9054632726',
        ];

        $this->postJson('/api/v1/auth/request-otp', $payload)->assertOk();

        $this->postJson('/api/v1/auth/verify-otp', $payload + ['code' => '1234'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Rajvi')
            ->assertJsonPath('data.user.role.slug', 'marketplace_user')
            ->assertJsonPath('data.user.permissions.0', 'designs.read')
            ->assertJsonPath('data.user.permissions.1', 'designs.create')
            ->assertJsonStructure(['data' => ['accessToken']]);

        $this->assertDatabaseHas('users', [
            'phone_key' => '+919054632726',
            'role_id' => Role::query()->where('slug', 'marketplace_user')->value('id'),
        ]);
    }

    public function test_missing_resources_use_the_standard_not_found_response(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/designs/design_missing')
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'code' => 'design_not_found',
            ]);
    }

    public function test_forbidden_mutations_use_the_standard_authorization_response(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $design = $this->createDesign($owner);
        Sanctum::actingAs($viewer);

        $this->deleteJson('/api/v1/designs/'.$design->public_id)
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'code' => 'design_delete_forbidden',
            ]);
    }

    public function test_design_lists_include_standardized_pagination_metadata_and_links(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 3) as $index) {
            $this->createDesign($user, ['title' => 'Design '.$index]);
        }
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/designs?per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJson([
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 2,
                    'total' => 3,
                    'last_page' => 2,
                    'from' => 1,
                    'to' => 2,
                ],
            ])
            ->assertJsonStructure([
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }

    public function test_design_create_preserves_the_complete_mobile_form_contract(): void
    {
        $user = User::factory()->create();
        $this->seed(DesignCatalogSeeder::class);
        $this->seed(DesignFormOptionSeeder::class);
        $this->seed(DesignFileTypeSeeder::class);
        $preview = $this->upload($user, 'public', 'previews/'.$user->public_id.'/front.jpg', 'photo', 'front.jpg');
        $stitch = $this->upload($user, 'local', 'stitch/'.$user->public_id.'/floral.dst', 'stitch', 'floral.dst');
        $uploads = app(UploadService::class);
        Sanctum::actingAs($user);

        $payload = [
            'machineTypeId' => 'machine-embroidery',
            'categoryId' => 'blouse-top',
            'typeId' => 'neckline',
            'designFileTypeId' => 'dst',
            'showPrice' => false,
            'pricePaise' => 29900,
            'details' => [[
                'names' => ['Floral neckline', 'Rose neckline'],
                'stitch' => '24000',
                'needle' => 12,
                'area' => '6 x 6',
                'height' => '6',
                'width' => '6',
            ]],
            'images' => [[
                'name' => 'front.jpg',
                'uri' => $uploads->publicUrl($preview),
            ]],
            'files' => [[
                'name' => 'floral.dst',
                'uri' => $uploads->publicUrl($stitch),
            ]],
            'title' => 'Floral neckline',
            'size' => '6 x 6',
            'color' => 'Gold',
            'imageUri' => $uploads->publicUrl($preview),
            'fileName' => 'floral.dst',
            'fileUri' => $uploads->publicUrl($stitch),
        ];

        $response = $this->postJson('/api/v1/designs', $payload)
            ->assertCreated()
            ->assertJsonPath('data.machineId', 'embroidery')
            ->assertJsonPath('data.machineTypeId', 'machine-embroidery')
            ->assertJsonPath('data.categoryId', 'blouse-top')
            ->assertJsonPath('data.typeId', 'neckline')
            ->assertJsonPath('data.designFileTypeId', 'dst')
            ->assertJsonPath('data.designFileTypeName', 'DST')
            ->assertJsonPath('data.designFileExtension', 'dst')
            ->assertJsonPath('data.showPrice', false)
            ->assertJsonPath('data.details.0.names.1', 'Rose neckline')
            ->assertJsonPath('data.details.0.needle', 12)
            ->assertJsonPath('data.images.0.name', 'front.jpg')
            ->assertJsonPath('data.files.0.name', 'floral.dst')
            ->assertJsonPath('data.files.0.displayName', 'Floral neckline.dst (.dst)')
            ->assertJsonPath('data.displayName', 'Floral neckline.dst (.dst)')
            ->assertJsonPath('data.title', 'Floral neckline')
            ->assertJsonPath('data.size', '6 × 6');

        $designId = $response->json('data.id');
        $this->assertDatabaseHas('designs', [
            'public_id' => $designId,
            'machine_type_id' => 'machine-embroidery',
            'category_id' => 'blouse-top',
            'show_price' => false,
            'design_file_type_id' => DesignFileType::query()->where('public_id', 'dst')->value('id'),
        ]);

        $created = $response->json('data');
        $updatePayload = $payload;
        $updatePayload['title'] = 'Updated floral neckline';
        $updatePayload['details'][0]['names'][0] = 'Updated floral neckline';
        $updatePayload['images'] = $created['images'];
        $updatePayload['files'] = $created['files'];
        $updatePayload['imageUri'] = $created['imageUri'];
        $updatePayload['fileName'] = $created['fileName'];
        $updatePayload['fileUri'] = $created['fileUri'];

        $this->patchJson('/api/v1/designs/'.$designId, $updatePayload)
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated floral neckline')
            ->assertJsonPath('data.files.0.uri', $created['files'][0]['uri']);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/designs/'.$designId)
            ->assertOk()
            ->assertJsonPath('data.files.0.name', 'floral.dst')
            ->assertJsonPath('data.files.0.uri', null)
            ->assertJsonPath('data.fileUri', null);
    }

    public function test_design_options_are_backend_managed_ordered_and_non_paginated(): void
    {
        $this->seed(DesignFormOptionSeeder::class);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/catalog/design-options')
            ->assertOk()
            ->assertJsonPath('message', 'Design options retrieved successfully.')
            ->assertJsonPath('data.machines.0.id', 'embroidery')
            ->assertJsonPath('data.machines.0.machineTypeIds.0', 'machine-embroidery')
            ->assertJsonFragment(['id' => 'machine-embroidery', 'name' => 'Multi Head'])
            ->assertJsonFragment(['id' => 'sequin', 'name' => 'Sequin'])
            ->assertJsonFragment(['id' => 'multi-sequin', 'name' => 'Multi Sequin'])
            ->assertJsonFragment(['id' => 'air-jet-loom', 'name' => 'Air Jet Loom'])
            ->assertJsonFragment(['id' => 'dobby-air-jet', 'machineId' => 'air-jet-loom'])
            ->assertJsonFragment(['id' => 'jacquard-air-jet', 'machineId' => 'air-jet-loom'])
            ->assertJsonPath('data.machineTypes.0.id', 'machine-embroidery')
            ->assertJsonPath('data.machineTypes.0.machineId', 'embroidery')
            ->assertJsonPath('data.categories.0.id', 'saree')
            ->assertJsonPath('data.categories.0.machineTypeIds.0', 'machine-embroidery')
            ->assertJsonMissingPath('meta')
            ->assertJsonMissingPath('links');
    }

    public function test_design_create_and_update_support_multiple_design_types(): void
    {
        $user = User::factory()->create();
        $this->seed(DesignCatalogSeeder::class);
        $this->seed(DesignFormOptionSeeder::class);
        $this->seed(DesignFileTypeSeeder::class);
        $preview = $this->upload($user, 'public', 'previews/'.$user->public_id.'/multi.jpg', 'photo', 'multi.jpg');
        $stitch = $this->upload($user, 'local', 'stitch/'.$user->public_id.'/multi.emb', 'stitch', 'multi.emb');
        $uploads = app(UploadService::class);
        Sanctum::actingAs($user);

        $payload = [
            'machineTypeId' => 'machine-embroidery',
            'categoryId' => 'blouse-top',
            'designFileTypeId' => 'emb',
            'showPrice' => true,
            'pricePaise' => 19900,
            'designTypes' => [
                [
                    'typeId' => 'neckline',
                    'details' => [
                        'nameOrNumber' => 'Neck 101',
                        'stitchCount' => 12000,
                        'needle' => 8,
                        'area' => 24.5,
                        'height' => 6.5,
                        'width' => 8,
                    ],
                ],
                [
                    'typeId' => 'sleeve',
                    'details' => [
                        'nameOrNumber' => 'Sleeve 202',
                        'stitchCount' => 9000,
                        'needle' => 6,
                        'area' => 18,
                        'height' => 5,
                        'width' => 7,
                    ],
                ],
            ],
            'images' => [[
                'name' => 'multi.jpg',
                'uri' => $uploads->publicUrl($preview),
            ]],
            'files' => [[
                'name' => 'multi.emb',
                'uri' => $uploads->publicUrl($stitch),
            ]],
        ];

        $response = $this->postJson('/api/v1/designs', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Neck 101')
            ->assertJsonPath('data.typeId', 'neckline')
            ->assertJsonPath('data.designTypes.0.typeId', 'neckline')
            ->assertJsonPath('data.designTypes.0.details.needle', 8)
            ->assertJsonPath('data.designTypes.1.typeId', 'sleeve')
            ->assertJsonPath('data.designTypes.1.details.nameOrNumber', 'Sleeve 202');

        $designId = $response->json('data.id');
        $this->assertDatabaseCount('design_variants', 2);

        $payload['designTypes'] = [$payload['designTypes'][1]];
        $payload['designTypes'][0]['details']['nameOrNumber'] = 'Sleeve updated';

        $this->patchJson('/api/v1/designs/'.$designId, $payload)
            ->assertOk()
            ->assertJsonCount(1, 'data.designTypes')
            ->assertJsonPath('data.designTypes.0.typeId', 'sleeve')
            ->assertJsonPath('data.title', 'Sleeve updated');

        $this->assertDatabaseCount('design_variants', 1);
    }

    public function test_design_options_can_be_loaded_from_separate_catalog_endpoints(): void
    {
        $this->seed(DesignFormOptionSeeder::class);
        $this->seed(DesignCatalogSeeder::class);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/catalog/machines')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'embroidery');

        $this->getJson('/api/v1/catalog/machines/embroidery/machine-types')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'machine-embroidery')
            ->assertJsonPath('data.0.machineId', 'embroidery');

        $this->getJson('/api/v1/catalog/machines/air-jet-loom/machine-types')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'dobby-air-jet')
            ->assertJsonPath('data.1.id', 'jacquard-air-jet');

        $this->getJson('/api/v1/catalog/machine-types/machine-embroidery/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'saree')
            ->assertJsonPath('data.0.machineTypeId', 'machine-embroidery');

        $this->getJson('/api/v1/catalog/machine-types/sequin/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 'saree')
            ->assertJsonPath('data.1.id', 'blouse-top');

        $this->getJson('/api/v1/catalog/machine-types/multi-sequin/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'saree');

        $this->getJson('/api/v1/catalog/machine-types/dobby-air-jet/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.id', 'general');

        $this->getJson('/api/v1/catalog/machine-types/jacquard-air-jet/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'saree');

        $this->getJson('/api/v1/catalog/categories/saree/types')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'pallu')
            ->assertJsonPath('data.0.categoryId', 'saree');
    }

    public function test_design_file_types_are_active_ordered_and_non_paginated(): void
    {
        $this->seed(DesignFileTypeSeeder::class);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/catalog/design-file-types')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Design file types retrieved successfully.')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', 'emb')
            ->assertJsonPath('data.0.name', 'EMB')
            ->assertJsonPath('data.0.extension', 'emb')
            ->assertJsonPath('data.1.id', 'dst')
            ->assertJsonPath('data.2.id', 'dhb')
            ->assertJsonMissingPath('meta')
            ->assertJsonMissingPath('links');
    }

    public function test_design_display_name_uses_selected_and_actual_file_extensions(): void
    {
        $this->seed(DesignFileTypeSeeder::class);
        $fileType = DesignFileType::query()->where('public_id', 'dst')->firstOrFail();
        $design = $this->createDesign(User::factory()->create(), [
            'title' => 'Abcd',
            'design_file_type_id' => $fileType->id,
            'file_name' => 'original.emb',
        ]);

        $this->assertSame('Abcd.dst (.emb)', $design->display_name);
    }

    public function test_nested_design_validation_errors_use_precise_field_paths(): void
    {
        $user = User::factory()->create();
        $type = $this->designType();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/designs', [
            'typeId' => $type->public_id,
            'pricePaise' => 29900,
            'title' => 'Broken design',
            'size' => '6 x 6',
            'color' => 'Gold',
            'imageUri' => 'https://example.test/front.jpg',
            'fileName' => 'broken.dst',
            'fileUri' => 'https://example.test/broken.dst',
            'details' => [[
                'names' => ['Broken design'],
                'needle' => 12,
                'area' => '6 x 6',
                'height' => '6',
                'width' => '6',
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['details.0.stitch']]);
    }

    public function test_non_image_design_files_are_not_restricted_by_mime_type(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/v1/uploads', [
            'file' => UploadedFile::fake()->create('machine-design.pes', 6000, 'application/x-custom-design'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('uploads', [
            'user_id' => $user->id,
            'kind' => 'stitch',
            'original_name' => 'machine-design.pes',
        ]);
    }

    public function test_additional_raster_image_types_are_accepted(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');

        $this->post('/api/v1/uploads', [
            'file' => UploadedFile::fake()->createWithContent('preview.gif', $gif),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('uploads', [
            'user_id' => $user->id,
            'kind' => 'photo',
            'original_name' => 'preview.gif',
            'mime' => 'image/gif',
        ]);
    }

    public function test_rate_limits_use_the_standard_error_response(): void
    {
        Route::middleware('throttle:1,1')->get('/api/v1/test-rate-limit', function () {
            return ApiResponse::success('Allowed.');
        });

        $this->getJson('/api/v1/test-rate-limit')->assertOk();
        $this->getJson('/api/v1/test-rate-limit')
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'code' => 'rate_limited',
            ]);
    }

    public function test_conflicts_and_unexpected_exceptions_do_not_expose_internal_details(): void
    {
        Route::get('/api/v1/test-conflict', fn () => abort(409, 'Sensitive conflict detail'));
        Route::get('/api/v1/test-failure', fn () => throw new RuntimeException('Sensitive internal detail'));

        $this->getJson('/api/v1/test-conflict')
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict')
            ->assertDontSee('Sensitive conflict detail');

        $this->getJson('/api/v1/test-failure')
            ->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'code' => 'internal_error',
            ])
            ->assertDontSee('Sensitive internal detail')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace');
    }

    private function createDesign(User $owner, array $attributes = []): Design
    {
        $type = $this->designType();

        return Design::query()->create(array_merge([
            'user_id' => $owner->id,
            'design_type_id' => $type->id,
            'title' => 'Test Design',
            'price_paise' => 1000,
            'size' => '6 inch hoop',
            'color' => 'Gold',
            'preview_path' => null,
            'stitch_path' => null,
            'file_name' => null,
        ], $attributes));
    }

    private function designType(): DesignType
    {
        $category = DesignCategory::query()->firstOrCreate(
            ['name' => 'Test Category'],
            ['is_active' => true, 'sort_order' => 1],
        );

        return DesignType::query()->firstOrCreate(
            ['design_category_id' => $category->id, 'name' => 'Test Type'],
            ['is_active' => true, 'sort_order' => 1],
        );
    }

    private function upload(
        User $user,
        string $disk,
        string $path,
        string $kind,
        string $name,
    ): Upload {
        return Upload::query()->create([
            'user_id' => $user->id,
            'disk' => $disk,
            'path' => $path,
            'kind' => $kind,
            'original_name' => $name,
            'mime' => $kind === 'photo' ? 'image/jpeg' : 'application/octet-stream',
            'size_bytes' => 100,
        ]);
    }
}
