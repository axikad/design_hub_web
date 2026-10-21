<?php

namespace Tests\Feature\Api;

use App\Models\Design;
use App\Models\DesignType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DesignCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeFeedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_feed_groups_latest_scheduled_and_category_designs(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-21 10:00:00', 'Asia/Kolkata'));
        $this->seed(DesignCatalogSeeder::class);
        $owner = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $today = $this->design($owner, 'pallu', 'saree', 'Today design', '2026-10-21');
        $tomorrow = $this->design($owner, 'border', 'saree', 'Tomorrow design', '2026-10-22');
        $yesterday = $this->design($owner, 'neckline', 'blouse-top', 'Previous design', '2026-10-20');

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('message', 'Home designs retrieved successfully.')
            ->assertJsonPath('data.latest.total', 2)
            ->assertJsonPath('data.latest.items.0.id', $today->public_id)
            ->assertJsonPath('data.today.total', 1)
            ->assertJsonPath('data.today.items.0.id', $today->public_id)
            ->assertJsonPath('data.tomorrow.total', 1)
            ->assertJsonPath('data.tomorrow.items.0.id', $tomorrow->public_id)
            ->assertJsonPath('data.categories.0.id', 'saree')
            ->assertJsonPath('data.categories.0.total', 1)
            ->assertJsonPath('data.categories.1.id', 'blouse-top')
            ->assertJsonPath('data.categories.1.items.0.id', $yesterday->public_id)
            ->assertJsonStructure([
                'data' => [
                    'latest' => ['total', 'items', 'hasMore', 'viewMoreUrl'],
                    'today' => ['total', 'items', 'hasMore', 'viewMoreUrl'],
                    'tomorrow' => ['total', 'items', 'hasMore', 'viewMoreUrl'],
                    'categories' => [['id', 'name', 'total', 'items', 'hasMore', 'viewMoreUrl']],
                ],
            ]);

        $this->getJson('/api/v1/designs?section=tomorrow')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tomorrow->public_id);

        $this->getJson('/api/v1/designs?categoryId=saree')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $today->public_id);
    }

    private function design(
        User $owner,
        string $typeId,
        string $categoryId,
        string $title,
        string $publishDate,
    ): Design {
        return Design::query()->create([
            'user_id' => $owner->id,
            'design_type_id' => DesignType::query()->where('public_id', $typeId)->value('id'),
            'category_id' => $categoryId,
            'title' => $title,
            'price_paise' => 1000,
            'size' => '6 × 6',
            'color' => '',
            'publish_date' => $publishDate,
        ]);
    }
}
