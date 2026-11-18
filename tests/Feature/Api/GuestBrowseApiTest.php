<?php

namespace Tests\Feature\Api;

use App\Models\Design;
use App\Models\Folder;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestBrowseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_home_designs_and_design_details_without_private_access(): void
    {
        $owner = User::factory()->create([
            'country_code' => '+91',
            'mobile_number' => '9876543210',
            'phone_key' => '+919876543210',
        ]);
        $design = Design::query()->create([
            'user_id' => $owner->id,
            'title' => 'Guest visible design',
            'price_paise' => 12500,
            'size' => '6 x 6',
            'color' => 'Gold',
            'preview_path' => 'previews/guest-visible.jpg',
            'stitch_path' => 'designs/private.dst',
            'file_name' => 'private.dst',
        ]);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.latest.items.0.id', $design->public_id);

        $this->getJson('/api/v1/designs')
            ->assertOk()
            ->assertJsonPath('data.0.id', $design->public_id)
            ->assertJsonPath('data.0.pricePaise', 12500)
            ->assertJsonPath('data.0.fileUri', null)
            ->assertJsonPath('data.0.ownerKey', null);

        $this->getJson('/api/v1/designs/'.$design->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $design->public_id)
            ->assertJsonPath('data.fileUri', null)
            ->assertJsonPath('data.images.0.downloadUri', null)
            ->assertJsonPath('data.ownerKey', null);
    }

    public function test_guest_can_browse_folders_but_protected_actions_require_authentication(): void
    {
        $folder = Folder::query()->create([
            'name' => 'Guest visible folder',
            'price_paise' => 9900,
            'zip_name' => 'private.zip',
            'zip_path' => 'folders/private.zip',
        ]);

        $this->getJson('/api/v1/folders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $folder->public_id)
            ->assertJsonMissingPath('data.0.downloadUri');

        $this->getJson('/api/v1/folders/'.$folder->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $folder->public_id)
            ->assertJsonMissingPath('data.downloadUri');

        foreach ([
            ['post', '/api/v1/payments/orders'],
            ['post', '/api/v1/designs/'.$this->missingDesignId().'/download'],
            ['put', '/api/v1/users/me/saved-designs/'.$this->missingDesignId()],
            ['post', '/api/v1/folders/'.$folder->public_id.'/download'],
        ] as [$method, $uri]) {
            $this->json($method, $uri)
                ->assertUnauthorized()
                ->assertJson([
                    'success' => false,
                    'message' => 'Please sign in to continue.',
                    'code' => 'AUTH_REQUIRED',
                ]);
        }
    }

    public function test_public_browse_uses_machine_preferences_when_a_valid_token_is_present(): void
    {
        $firstMachine = Machine::query()->create(['name' => 'Embroidery', 'is_active' => true]);
        $secondMachine = Machine::query()->create(['name' => 'Knitting', 'is_active' => true]);
        $owner = User::factory()->create();
        $firstDesign = Design::query()->create([
            'user_id' => $owner->id,
            'machine_id' => $firstMachine->id,
            'title' => 'Embroidery design',
            'price_paise' => 10000,
            'size' => '6 x 6',
            'color' => '',
        ]);
        $secondDesign = Design::query()->create([
            'user_id' => $owner->id,
            'machine_id' => $secondMachine->id,
            'title' => 'Knitting design',
            'price_paise' => 10000,
            'size' => '6 x 6',
            'color' => '',
        ]);

        $this->getJson('/api/v1/designs')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $viewer = User::factory()->create();
        $viewer->machines()->attach($firstMachine);
        $headers = ['Authorization' => 'Bearer '.$viewer->createToken('guest-upgrade-test')->plainTextToken];

        $this->getJson('/api/v1/designs', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstDesign->public_id)
            ->assertJsonMissing(['id' => $secondDesign->public_id]);

        $this->getJson('/api/v1/catalog/machines', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstMachine->public_id);
    }

    private function missingDesignId(): string
    {
        return 'design_missing';
    }
}
