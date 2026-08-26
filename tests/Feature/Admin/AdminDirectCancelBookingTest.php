<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDirectCancelBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_direct_cancel_hard_fails_without_supplier_connection_id(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'pnr' => 'SBXQA1',
            'supplier' => 'sabre',
            'meta' => ['supplier_provider' => 'sabre'],
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('admin.bookings.admin-direct-cancel', $booking),
            ['reason' => 'QA missing connection id'],
        );

        $response->assertStatus(409);
        $booking->refresh();
        $this->assertNotSame(BookingStatus::Cancelled, $booking->status);
    }

    public function test_customer_cannot_call_admin_direct_cancel(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::factory()->create(['account_type' => AccountType::Customer]);
        $agency = Agency::query()->firstOrFail();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'name' => 'sabre-sandbox-qa',
        ]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'pnr' => 'SBXQA2',
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $connection->id,
            ],
        ]);

        $this->actingAs($customer)->postJson(
            route('admin.bookings.admin-direct-cancel', $booking),
            ['reason' => 'customer must not cancel'],
        )->assertForbidden();
    }

    public function test_sandbox_qa_guard_blocks_when_connection_points_at_production_host(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $agency = Agency::query()->firstOrFail();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.platform.sabre.com',
            'name' => 'misconfigured-sandbox',
        ]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'pnr' => 'SBXQA3',
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $connection->id,
            ],
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('admin.bookings.admin-direct-cancel', $booking),
            ['reason' => 'QA host guard'],
        );

        $response->assertStatus(409);
        $this->assertStringContainsString('QA_LIFECYCLE_PRODUCTION_HOST_GUARD', (string) $response->json('message'));
        $booking->refresh();
        $this->assertNotSame(BookingStatus::Cancelled, $booking->status);
    }
}
