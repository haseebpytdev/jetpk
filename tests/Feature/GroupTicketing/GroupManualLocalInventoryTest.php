<?php

namespace Tests\Feature\GroupTicketing;

use App\Enums\AccountType;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\GroupTicketing\GroupInventoryAvailabilityService;
use App\Services\GroupTicketing\GroupInventorySearchService;
use App\Services\GroupTicketing\GroupManualLocalInventoryService;
use App\Support\GroupTicketing\GroupManualLocalVisibility;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupManualLocalInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'ota.group_ticketing.inventory_search_sync_enabled' => false,
            'ota.group_ticketing.realtime_search_enabled' => false,
            'ota.group_ticketing.require_live_provider_for_public_results' => false,
            'ota.group_ticketing.require_live_provider_for_reservation' => true,
            'ota.group_ticketing.block_booking_when_provider_unavailable' => true,
            'suppliers.al_haider.enabled' => true,
            'suppliers.al_haider.booking_enabled' => true,
            'ota.group_ticketing.manual_local_qa_viewer_emails' => ['qa.customer@example.test'],
        ]);
    }

    public function test_manual_local_create_has_no_supplier_binding(): void
    {
        $inventory = app(GroupManualLocalInventoryService::class)->create([
            'title' => 'QA GROUP A — B2C',
            'sector' => 'LHE-DXB',
            'departure_date' => now()->addMonths(2)->toDateString(),
            'total_seats' => 5,
            'price' => 55000,
            'audience' => 'b2c',
            'is_active' => true,
        ]);

        $this->assertTrue($inventory->isManualLocal());
        $this->assertSame('MANUAL_LOCAL', $inventory->snapshot['qa_group_source'] ?? null);
        $this->assertArrayHasKey('supplier_connection_id', $inventory->snapshot);
        $this->assertNull($inventory->snapshot['supplier_connection_id']);
        $this->assertStringStartsWith('QA-ML-', (string) $inventory->public_id);
    }

    public function test_manual_local_hidden_from_guest_search(): void
    {
        app(GroupManualLocalInventoryService::class)->create([
            'title' => 'QA GROUP A — B2C',
            'sector' => 'LHE-DXB',
            'departure_date' => now()->addMonths(2)->toDateString(),
            'total_seats' => 5,
            'price' => 55000,
            'audience' => 'b2c',
            'is_active' => true,
        ]);

        $results = app(GroupInventorySearchService::class)->search(['sector' => 'LHE-DXB']);
        $this->assertTrue($results->every(fn (GroupInventory $row) => ! $row->isManualLocal()));
    }

    public function test_manual_local_visible_to_allowlisted_customer(): void
    {
        $inventory = app(GroupManualLocalInventoryService::class)->create([
            'title' => 'QA GROUP A — B2C',
            'sector' => 'LHE-DXB',
            'departure_date' => now()->addMonths(2)->toDateString(),
            'total_seats' => 5,
            'price' => 55000,
            'audience' => 'b2c',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'qa.customer@example.test',
            'account_type' => AccountType::Customer,
        ]);
        $this->actingAs($user);

        $this->assertTrue(GroupManualLocalVisibility::userCanViewManualLocal($user));
        $results = app(GroupInventorySearchService::class)->search(['sector' => 'LHE-DXB']);
        $this->assertTrue($results->contains(fn (GroupInventory $row) => $row->is($inventory)));
    }

    public function test_manual_local_revalidate_skips_live_provider(): void
    {
        $inventory = app(GroupManualLocalInventoryService::class)->create([
            'title' => 'QA GROUP A — B2C',
            'sector' => 'LHE-DXB',
            'departure_date' => now()->addMonths(2)->toDateString(),
            'total_seats' => 5,
            'price' => 55000,
            'audience' => 'b2c',
            'is_active' => true,
        ]);

        $result = app(GroupInventoryAvailabilityService::class)->revalidate($inventory, 2);
        $this->assertTrue($result['ok']);
        $this->assertTrue($result['provider_confirmed']);
        $this->assertSame(5, $result['available_seats']);

        $blocked = app(GroupInventoryAvailabilityService::class)->revalidate($inventory, 6);
        $this->assertFalse($blocked['ok']);
        $this->assertTrue($blocked['insufficient_seats']);
    }
}
