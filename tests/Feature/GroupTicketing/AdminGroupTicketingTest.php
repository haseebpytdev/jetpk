<?php

namespace Tests\Feature\GroupTicketing;

use App\Enums\GroupHomepageTileTargetType;
use App\Models\GroupCategory;
use App\Models\GroupHomepageTile;
use App\Models\GroupInventory;
use App\Support\GroupTicketing\GroupHomepageTilePresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminGroupTicketingTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_tiles_page_shows_virtual_all_groups_first(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.group-ticketing.tiles.index'))
            ->assertOk()
            ->assertSee('All Groups', false)
            ->assertSee('Tiles are generated from live group inventory', false)
            ->assertSee('Save all homepage tiles', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertDontSee('Add tile', false)
            ->assertDontSee('btn btn-primary btn-sm w-100">Save</button>', false);
    }

    public function test_admin_tiles_page_lists_inventory_backed_categories_without_manual_rows(): void
    {
        $admin = $this->platformAdmin();
        $this->seedInventoryCategories();

        $this->actingAs($admin)
            ->get(route('admin.group-ticketing.tiles.index'))
            ->assertOk()
            ->assertSeeInOrder(['All Groups', 'KSA Groups', 'UAE Groups', 'Muscat Groups'], false);

        $this->assertSame(0, GroupHomepageTile::query()->count());
    }

    public function test_tile_upsert_creates_override_and_homepage_reflects_title(): void
    {
        $admin = $this->platformAdmin();
        $this->seedInventoryCategories();

        $this->actingAs($admin)
            ->post(route('admin.group-ticketing.tiles.upsert'), [
                'target_type' => GroupHomepageTileTargetType::Category->value,
                'target_value' => 'ksa-oneway',
                'title' => 'Custom KSA Admin Tile',
                'sort_order' => 5,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.group-ticketing.tiles.index'));

        $this->assertDatabaseHas('group_homepage_tiles', [
            'target_type' => GroupHomepageTileTargetType::Category->value,
            'target_value' => 'ksa-oneway',
            'title' => 'Custom KSA Admin Tile',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Custom KSA Admin Tile', false);
    }

    public function test_tile_upsert_updates_existing_override(): void
    {
        $admin = $this->platformAdmin();
        $this->seedInventoryCategories();

        GroupHomepageTile::query()->create([
            'title' => 'First Title',
            'target_type' => GroupHomepageTileTargetType::Category,
            'target_value' => 'uae',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.group-ticketing.tiles.upsert'), [
                'target_type' => GroupHomepageTileTargetType::Category->value,
                'target_value' => 'uae',
                'title' => 'Updated UAE Tile',
                'sort_order' => 2,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.group-ticketing.tiles.index'));

        $this->assertSame(1, GroupHomepageTile::query()->count());
        $this->assertDatabaseHas('group_homepage_tiles', [
            'target_value' => 'uae',
            'title' => 'Updated UAE Tile',
        ]);
    }

    public function test_inactive_category_override_hides_tile_on_homepage_but_shows_on_admin(): void
    {
        $admin = $this->platformAdmin();
        $this->seedInventoryCategories();

        $this->actingAs($admin)
            ->post(route('admin.group-ticketing.tiles.upsert'), [
                'target_type' => GroupHomepageTileTargetType::Category->value,
                'target_value' => 'muscat',
                'title' => '',
                'sort_order' => 0,
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.group-ticketing.tiles.index'));

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Muscat Groups', false);

        $this->actingAs($admin)
            ->get(route('admin.group-ticketing.tiles.index'))
            ->assertOk()
            ->assertSee('Muscat Groups', false)
            ->assertSee('Hidden on homepage', false);
    }

    public function test_group_inventory_sync_is_scheduled_daily_and_release_expired_every_minute(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('group-ticketing:sync-inventory', $output);
        $this->assertStringContainsString('group-ticketing:release-expired', $output);

        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $syncEvent = $events->first(fn (Event $event): bool => str_contains($event->command ?? '', 'group-ticketing:sync-inventory'));
        $releaseEvent = $events->first(fn (Event $event): bool => str_contains($event->command ?? '', 'group-ticketing:release-expired'));

        $this->assertNotNull($syncEvent);
        $this->assertNotNull($releaseEvent);
        $this->assertSame('0 2 * * *', $syncEvent->expression);
        $this->assertSame('* * * * *', $releaseEvent->expression);
    }

    public function test_admin_sidebar_contains_group_ticketing_navigation_links(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.group-ticketing.index'))
            ->assertOk()
            ->assertSee(route('admin.group-ticketing.index', [], false), false)
            ->assertSee(route('admin.group-ticketing.tiles.index', [], false), false)
            ->assertSee(route('admin.group-ticketing.inventory.index', [], false), false)
            ->assertSee(route('admin.group-bookings.index', [], false), false)
            ->assertSee('Group Ticketing', false)
            ->assertSee('Homepage Tiles', false);
    }

    public function test_api_categories_page_is_read_only(): void
    {
        $admin = $this->platformAdmin();
        $this->seedInventoryCategories();

        $this->actingAs($admin)
            ->get(route('admin.group-ticketing.categories.index'))
            ->assertOk()
            ->assertSee('API Categories', false)
            ->assertSee('ksa-oneway', false)
            ->assertSee('KSA Groups', false)
            ->assertDontSee('Add category', false);
    }

    public function test_batch_save_updates_title_order_and_active_for_multiple_tiles(): void
    {
        $admin = $this->platformAdmin();
        $this->seedInventoryCategories();

        $this->actingAs($admin)
            ->post(route('admin.group-ticketing.tiles.batch-upsert'), [
                'tiles' => [
                    'all' => [
                        'target_type' => GroupHomepageTileTargetType::All->value,
                        'title' => 'Every Group Deal',
                        'sort_order' => 0,
                        'is_active' => 1,
                    ],
                    'category:ksa-oneway' => [
                        'target_type' => GroupHomepageTileTargetType::Category->value,
                        'target_value' => 'ksa-oneway',
                        'title' => 'Custom KSA Admin Tile',
                        'sort_order' => 5,
                        'is_active' => 1,
                    ],
                    'category:uae' => [
                        'target_type' => GroupHomepageTileTargetType::Category->value,
                        'target_value' => 'uae',
                        'title' => 'UAE Priority',
                        'sort_order' => 10,
                        'is_active' => 0,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.group-ticketing.tiles.index'))
            ->assertSessionHas('status', 'tiles-saved');

        $this->assertDatabaseHas('group_homepage_tiles', [
            'target_type' => GroupHomepageTileTargetType::All->value,
            'target_value' => null,
            'title' => 'Every Group Deal',
        ]);
        $this->assertDatabaseHas('group_homepage_tiles', [
            'target_type' => GroupHomepageTileTargetType::Category->value,
            'target_value' => 'ksa-oneway',
            'title' => 'Custom KSA Admin Tile',
            'sort_order' => 5,
        ]);
        $this->assertDatabaseHas('group_homepage_tiles', [
            'target_type' => GroupHomepageTileTargetType::Category->value,
            'target_value' => 'uae',
            'title' => 'UAE Priority',
            'is_active' => false,
        ]);
    }

    public function test_batch_save_accepts_nested_image_upload_for_all_groups(): void
    {
        Storage::fake('public');

        $admin = $this->platformAdmin();
        $this->seedInventoryCategories();

        $file = UploadedFile::fake()->image('all-groups.jpg', 600, 500);

        $this->actingAs($admin)
            ->post(route('admin.group-ticketing.tiles.batch-upsert'), [
                'tiles' => [
                    'all' => [
                        'target_type' => GroupHomepageTileTargetType::All->value,
                        'title' => 'All Groups',
                        'sort_order' => 0,
                        'is_active' => 1,
                        'image' => $file,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.group-ticketing.tiles.index'))
            ->assertSessionHas('status', 'tiles-saved');

        $tile = GroupHomepageTile::query()->where('target_type', GroupHomepageTileTargetType::All)->first();
        $this->assertNotNull($tile);
        $this->assertNotNull($tile->image_path);
        $this->assertStringStartsWith('group-homepage-tiles/', $tile->image_path);
        Storage::disk('public')->assertExists($tile->image_path);

        $presenter = app(GroupHomepageTilePresenter::class);
        $homeTiles = $presenter->presentForHome();
        $this->assertStringContainsString(
            'storage/'.$tile->image_path,
            (string) $homeTiles->first()['image_url']
        );
    }

    public function test_batch_save_accepts_nested_image_upload_for_category_tile(): void
    {
        Storage::fake('public');

        $admin = $this->platformAdmin();
        $this->seedInventoryCategories();

        $file = UploadedFile::fake()->image('ksa-groups.png', 640, 480);

        $this->actingAs($admin)
            ->post(route('admin.group-ticketing.tiles.batch-upsert'), [
                'tiles' => [
                    'category:ksa-oneway' => [
                        'target_type' => GroupHomepageTileTargetType::Category->value,
                        'target_value' => 'ksa-oneway',
                        'title' => 'KSA Groups',
                        'sort_order' => 1,
                        'is_active' => 1,
                        'image' => $file,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.group-ticketing.tiles.index'))
            ->assertSessionHas('status', 'tiles-saved');

        $tile = GroupHomepageTile::query()
            ->where('target_type', GroupHomepageTileTargetType::Category)
            ->where('target_value', 'ksa-oneway')
            ->first();

        $this->assertNotNull($tile);
        $this->assertStringStartsWith('group-homepage-tiles/', (string) $tile->image_path);
        Storage::disk('public')->assertExists($tile->image_path);

        $presenter = app(GroupHomepageTilePresenter::class);
        $adminTiles = $presenter->presentForAdmin();
        $ksaTile = $adminTiles->first(fn (array $row): bool => $row['target_value'] === 'ksa-oneway');
        $this->assertNotNull($ksaTile);
        $this->assertStringContainsString('storage/'.$tile->image_path, (string) $ksaTile['image_url']);
    }

    public function test_batch_save_preserves_existing_image_when_no_new_file_uploaded(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('group-homepage-tiles/existing-all.jpg', 'existing-image');

        $admin = $this->platformAdmin();
        $this->seedInventoryCategories();

        GroupHomepageTile::query()->create([
            'title' => 'All Groups',
            'image_path' => 'group-homepage-tiles/existing-all.jpg',
            'target_type' => GroupHomepageTileTargetType::All,
            'target_value' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.group-ticketing.tiles.batch-upsert'), [
                'tiles' => [
                    'all' => [
                        'target_type' => GroupHomepageTileTargetType::All->value,
                        'title' => 'Renamed All Groups',
                        'sort_order' => 0,
                        'is_active' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.group-ticketing.tiles.index'));

        $this->assertDatabaseHas('group_homepage_tiles', [
            'target_type' => GroupHomepageTileTargetType::All->value,
            'title' => 'Renamed All Groups',
            'image_path' => 'group-homepage-tiles/existing-all.jpg',
        ]);
        Storage::disk('public')->assertExists('group-homepage-tiles/existing-all.jpg');
    }

    public function test_batch_save_invalid_image_returns_validation_error_without_wiping_existing_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('group-homepage-tiles/existing-ksa.jpg', 'existing-image');

        $admin = $this->platformAdmin();
        $this->seedInventoryCategories();

        GroupHomepageTile::query()->create([
            'title' => 'KSA Groups',
            'image_path' => 'group-homepage-tiles/existing-ksa.jpg',
            'target_type' => GroupHomepageTileTargetType::Category,
            'target_value' => 'ksa-oneway',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.group-ticketing.tiles.index'))
            ->post(route('admin.group-ticketing.tiles.batch-upsert'), [
                'tiles' => [
                    'category:ksa-oneway' => [
                        'target_type' => GroupHomepageTileTargetType::Category->value,
                        'target_value' => 'ksa-oneway',
                        'title' => 'KSA Groups',
                        'sort_order' => 1,
                        'is_active' => 1,
                        'image' => UploadedFile::fake()->create('bad-file.pdf', 100, 'application/pdf'),
                    ],
                ],
            ])
            ->assertRedirect(route('admin.group-ticketing.tiles.index'))
            ->assertSessionHasErrors('tiles.category:ksa-oneway.image');

        $this->assertDatabaseHas('group_homepage_tiles', [
            'target_value' => 'ksa-oneway',
            'image_path' => 'group-homepage-tiles/existing-ksa.jpg',
        ]);
        Storage::disk('public')->assertExists('group-homepage-tiles/existing-ksa.jpg');
    }

    private function seedInventoryCategories(): void
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
            'synced_at' => now(),
        ]);
    }
}
