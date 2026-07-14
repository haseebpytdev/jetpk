<?php

namespace Tests\Feature\GroupTicketing;

use App\Enums\GroupHomepageTileTargetType;
use App\Models\GroupCategory;
use App\Models\GroupHomepageTile;
use App\Models\GroupInventory;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_all_groups_plus_inventory_backed_categories_only(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $ksa = GroupCategory::query()->create([
            'slug' => 'ksa-oneway',
            'name' => 'KSA ONEWAY',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $uae = GroupCategory::query()->create([
            'slug' => 'uae',
            'name' => 'UAE',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        GroupCategory::query()->create([
            'slug' => 'umrah',
            'name' => 'Umrah',
            'is_active' => true,
            'sort_order' => 3,
        ]);
        GroupCategory::query()->create([
            'slug' => 'featured',
            'name' => 'Featured',
            'is_active' => true,
            'sort_order' => 4,
        ]);

        $this->createInventory($ksa->id, 'KSA Package A', 'ALH-KSA-1');
        $this->createInventory($ksa->id, 'KSA Package B', 'ALH-KSA-2');
        $this->createInventory($uae->id, 'UAE Package', 'ALH-UAE-1');

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Group departures', false)
            ->assertSee('All Groups', false)
            ->assertSee('KSA Groups', false)
            ->assertSee('UAE Groups', false)
            ->assertDontSee('Umrah Groups', false)
            ->assertDontSee('>Featured<', false);
    }

    public function test_empty_category_does_not_appear_on_homepage(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $empty = GroupCategory::query()->create([
            'slug' => 'umrah',
            'name' => 'Umrah',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'sold-out',
            'public_id' => 'ALH-SOLD',
            'group_category_id' => $empty->id,
            'title' => 'Sold Out Group',
            'sector' => 'LHE-JED',
            'departure_date' => '2026-07-01',
            'total_seats' => 10,
            'held_seats' => 5,
            'sold_seats' => 5,
            'price' => 150000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('All Groups', false)
            ->assertDontSee('Umrah Groups', false);
    }

    public function test_category_tile_url_filters_search_results(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $ksa = GroupCategory::query()->create([
            'slug' => 'ksa-oneway',
            'name' => 'KSA ONEWAY',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $uae = GroupCategory::query()->create([
            'slug' => 'uae',
            'name' => 'UAE',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->createInventory($ksa->id, 'KSA Only Package', 'ALH-KSA-ONLY');
        $this->createInventory($uae->id, 'UAE Only Package', 'ALH-UAE-ONLY');

        $this->get('/groups/search?category=ksa-oneway')
            ->assertOk()
            ->assertSee('Showing 1 of 1 group departure', false)
            ->assertSee('data-testid="group-result-row"', false);
    }

    public function test_new_category_with_inventory_appears_without_manual_tile(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $muscat = GroupCategory::query()->create([
            'slug' => 'muscat',
            'name' => 'MUSCAT',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $this->createInventory($muscat->id, 'Muscat Package', 'ALH-MCT-1');

        $this->get('/')
            ->assertOk()
            ->assertSee('Muscat Groups', false);
    }

    public function test_admin_tile_override_supplies_custom_title(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $ksa = GroupCategory::query()->create([
            'slug' => 'ksa-oneway',
            'name' => 'KSA ONEWAY',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->createInventory($ksa->id, 'KSA Package', 'ALH-KSA-1');

        GroupHomepageTile::query()->create([
            'title' => 'Custom KSA Tile',
            'target_type' => GroupHomepageTileTargetType::Category,
            'target_value' => 'ksa-oneway',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Custom KSA Tile', false)
            ->assertDontSee('KSA Groups', false);
    }

    public function test_homepage_with_four_tiles_does_not_render_slider_arrows(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->seedFourInventoryCategories();

        $this->get('/')
            ->assertOk()
            ->assertSee('data-slider="0"', false)
            ->assertDontSee('ota-home-groups-preview-carousel__btn--prev', false)
            ->assertDontSee('ota-home-groups-preview-carousel__btn--next', false);
    }

    public function test_homepage_with_five_or_more_tiles_renders_slider_arrows(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->seedFourInventoryCategories();

        $extra = GroupCategory::query()->create([
            'slug' => 'europe',
            'name' => 'Europe',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $this->createInventory($extra->id, 'Europe Package', 'ALH-EU-1');

        $this->get('/')
            ->assertOk()
            ->assertSee('is-slider', false)
            ->assertSee('data-slider="1"', false)
            ->assertSee('ota-home-groups-preview-carousel__btn--prev', false)
            ->assertSee('ota-home-groups-preview-carousel__btn--next', false)
            ->assertSee('Europe Groups', false);
    }

    public function test_admin_tile_image_is_used_on_homepage(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->seedFourInventoryCategories();

        GroupHomepageTile::query()->create([
            'title' => 'All Groups',
            'image_path' => 'group-homepage-tiles/test-tile.jpg',
            'target_type' => GroupHomepageTileTargetType::All,
            'target_value' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('storage/group-homepage-tiles/test-tile.jpg', false);
    }

    public function test_missing_tile_image_renders_fallback_placeholder(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->seedFourInventoryCategories();

        $this->get('/')
            ->assertOk()
            ->assertSee('ota-home-groups-preview-tile__placeholder', false);
    }

    public function test_admin_tile_override_changes_title_and_order(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $ksa = GroupCategory::query()->create([
            'slug' => 'ksa-oneway',
            'name' => 'KSA ONEWAY',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $uae = GroupCategory::query()->create([
            'slug' => 'uae',
            'name' => 'UAE',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->createInventory($ksa->id, 'KSA Package', 'ALH-KSA-1');
        $this->createInventory($uae->id, 'UAE Package', 'ALH-UAE-1');

        GroupHomepageTile::query()->create([
            'title' => 'KSA First Tile',
            'target_type' => GroupHomepageTileTargetType::Category,
            'target_value' => 'ksa-oneway',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        GroupHomepageTile::query()->create([
            'title' => 'UAE Priority Tile',
            'target_type' => GroupHomepageTileTargetType::Category,
            'target_value' => 'uae',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['UAE Priority Tile', 'KSA First Tile'], false);
    }

    public function test_inactive_category_override_is_hidden_on_homepage(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $muscat = GroupCategory::query()->create([
            'slug' => 'muscat',
            'name' => 'MUSCAT',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->createInventory($muscat->id, 'Muscat Package', 'ALH-MCT-1');

        GroupHomepageTile::query()->create([
            'title' => '',
            'target_type' => GroupHomepageTileTargetType::Category,
            'target_value' => 'muscat',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('All Groups', false)
            ->assertDontSee('Muscat Groups', false);
    }

    /**
     * @return array{ksa: GroupCategory, uae: GroupCategory, muscat: GroupCategory}
     */
    private function seedFourInventoryCategories(): array
    {
        $ksa = GroupCategory::query()->create([
            'slug' => 'ksa-oneway',
            'name' => 'KSA ONEWAY',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $uae = GroupCategory::query()->create([
            'slug' => 'uae',
            'name' => 'UAE',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $muscat = GroupCategory::query()->create([
            'slug' => 'muscat',
            'name' => 'MUSCAT',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $this->createInventory($ksa->id, 'KSA Package', 'ALH-KSA-1');
        $this->createInventory($uae->id, 'UAE Package', 'ALH-UAE-1');
        $this->createInventory($muscat->id, 'Muscat Package', 'ALH-MCT-1');

        return compact('ksa', 'uae', 'muscat');
    }

    private function createInventory(int $categoryId, string $title, string $publicId): GroupInventory
    {
        return GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => $publicId,
            'public_id' => $publicId,
            'group_category_id' => $categoryId,
            'title' => $title,
            'sector' => 'LHE-JED',
            'airline_name' => 'FLYNAS',
            'departure_date' => '2026-07-01',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 150000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);
    }
}
