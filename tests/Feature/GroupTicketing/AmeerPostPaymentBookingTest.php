<?php

namespace Tests\Feature\GroupTicketing;

use App\Enums\AccountType;
use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupBookingPassenger;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\GroupTicketing\GroupReservationService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmeerPostPaymentBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('suppliers.ameer_e_millat.enabled', true);
        Config::set('suppliers.ameer_e_millat.booking_enabled', true);
        Config::set('suppliers.ameer_e_millat.token', 'booking-test-token');
        Config::set('suppliers.ameer_e_millat.default_base_url', 'https://ameer.test');
        Config::set('suppliers.ameer_e_millat.create_booking_path', '/api/create/booking');
        Config::set('suppliers.ameer_e_millat.show_booking_path', '/api/show/booking/{id}');
        Config::set('suppliers.ameer_e_millat.group_detail_path', '/api/group/detail/{id}');
        Config::set('suppliers.ameer_e_millat.seats_path', '/api/available/seats/{id}');
        Config::set('suppliers.ameer_e_millat.airlines_path', '/api/available/airlines');
        Config::set('ota_client.modules.ameer_e_millat_group_ticketing', true);
        Config::set('ota.group_ticketing.require_live_provider_for_reservation', false);
        Config::set('ota.group_ticketing.block_booking_when_provider_unavailable', false);
    }

    public function test_local_hold_does_not_create_supplier_booking(): void
    {
        Http::fake($this->revalidationHttpFakes());

        $booking = $this->seedPendingBooking();

        app(GroupReservationService::class)->createReservation($booking);

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/create/booking'));
        $booking->refresh();
        $this->assertSame('local_hold_post_payment', $booking->meta['provider_hold_status'] ?? null);
        $this->assertNull($booking->supplier_reservation_id);
    }

    public function test_verify_payment_creates_supplier_booking_once(): void
    {
        Http::fake(array_merge($this->revalidationHttpFakes(), [
            'ameer.test/api/create/booking' => function ($request) {
                $payload = $request->data();
                $this->assertArrayHasKey('booking_details', $payload);
                $this->assertArrayNotHasKey('passengers', $payload);
                $this->assertSame(501, $payload['group_id']);
                $this->assertSame(501, $payload['agency_info']['group_id']);
                $this->assertSame('MR', $payload['booking_details'][0]['title']);
                $this->assertSame('Ali', $payload['booking_details'][0]['given_name']);
                $this->assertSame('Khan', $payload['booking_details'][0]['surname']);
                $this->assertSame('AB1234567', $payload['booking_details'][0]['passport_no']);
                $this->assertArrayHasKey('agent_name', $payload['agency_info']);
                $this->assertArrayHasKey('agency_name', $payload['agency_info']);
                $this->assertArrayHasKey('email', $payload['agency_info']);
                $this->assertArrayHasKey('mobile', $payload['agency_info']);
                $this->assertSame(1, $payload['agency_info']['adults']);
                $this->assertSame(0, $payload['agency_info']['child']);
                $this->assertSame(0, $payload['agency_info']['infant']);
                $this->assertArrayHasKey('agent_notes', $payload['agency_info']);

                return Http::response([
                    'error' => false,
                    'success' => true,
                    'message' => 'Booking created',
                    'data' => ['id' => 9001],
                ], 200);
            },
            'ameer.test/api/show/booking/9001' => Http::response([
                'error' => false,
                'success' => true,
                'data' => ['id' => 9001, 'status' => 'confirmed'],
            ], 200),
        ]));

        $booking = $this->seedPendingBooking();
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);

        $service = app(GroupReservationService::class);
        $service->createReservation($booking);
        $booking->refresh();
        $booking->update(['status' => GroupBookingStatus::ManualPaymentPendingReview]);

        $confirmed = $service->verifyPayment($booking, $admin);

        $this->assertSame(GroupBookingStatus::Confirmed, $confirmed->status);
        $this->assertSame('9001', $confirmed->supplier_reservation_id);
        $this->assertSame('9001', $confirmed->meta['supplier_booking_id'] ?? null);

        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/api/create/booking'), 1);
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/api/show/booking/9001'));
    }

    public function test_verify_payment_retry_does_not_duplicate_create_call(): void
    {
        Http::fake(array_merge($this->revalidationHttpFakes(), [
            'ameer.test/api/create/booking' => Http::response([
                'error' => false,
                'success' => true,
                'data' => ['id' => 9002],
            ], 200),
            'ameer.test/api/show/booking/9002' => Http::response([
                'error' => false,
                'success' => true,
                'data' => ['id' => 9002],
            ], 200),
        ]));

        $booking = $this->seedPendingBooking();
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $service = app(GroupReservationService::class);

        $service->createReservation($booking);
        $booking->refresh();
        $booking->update([
            'status' => GroupBookingStatus::ManualPaymentPendingReview,
            'meta' => ['supplier_booking_id' => '9002'],
            'supplier_reservation_id' => '9002',
        ]);

        $service->verifyPayment($booking, $admin);

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/create/booking'));
    }

    public function test_agency_info_counts_child_and_infant_passengers(): void
    {
        Http::fake(array_merge($this->revalidationHttpFakes(), [
            'ameer.test/api/create/booking' => function ($request) {
                $payload = $request->data();
                $this->assertSame(1, $payload['agency_info']['adults']);
                $this->assertSame(1, $payload['agency_info']['child']);
                $this->assertSame(1, $payload['agency_info']['infant']);
                $this->assertCount(3, $payload['booking_details']);
                $this->assertSame('CHD', $payload['booking_details'][1]['title']);
                $this->assertSame('INF', $payload['booking_details'][2]['title']);
                $this->assertArrayNotHasKey('passengers', $payload);

                return Http::response([
                    'error' => false,
                    'success' => true,
                    'data' => ['id' => 9003],
                ], 200);
            },
            'ameer.test/api/show/booking/9003' => Http::response([
                'error' => false,
                'success' => true,
                'data' => ['id' => 9003],
            ], 200),
        ]));

        $booking = $this->seedPendingBooking(withChildAndInfant: true);
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $service = app(GroupReservationService::class);
        $service->createReservation($booking);
        $booking->refresh();
        $booking->update(['status' => GroupBookingStatus::ManualPaymentPendingReview]);

        $confirmed = $service->verifyPayment($booking, $admin);
        $this->assertSame('9003', $confirmed->supplier_reservation_id);
    }

    private function seedPendingBooking(bool $withChildAndInfant = false): GroupBooking
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::factory()->create(['account_type' => AccountType::Customer]);

        $seatCount = $withChildAndInfant ? 3 : 1;
        $total = 99000 * $seatCount;

        $inventory = GroupInventory::query()->create([
            'supplier' => 'ameer_e_millat',
            'supplier_package_id' => '501',
            'public_id' => 'AEM-501',
            'title' => 'Ameer post-payment test',
            'sector' => 'SKT-SHJ',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 99000,
            'currency' => 'PKR',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $booking = GroupBooking::query()->create([
            'reference' => 'GRP-AEM-POSTPAY'.($withChildAndInfant ? '-MIX' : ''),
            'user_id' => $user->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::PendingPassengerDetails,
            'seat_count' => $seatCount,
            'total_amount' => $total,
            'currency' => 'PKR',
            'contact_name' => 'Ali Khan',
            'contact_email' => 'ali@example.test',
            'contact_phone' => '+923001234567',
            'reservation_created_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        GroupBookingPassenger::query()->create([
            'group_booking_id' => $booking->id,
            'title' => 'Mr',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'passport_number' => 'AB1234567',
            'passport_expiry' => '2030-01-01',
            'nationality' => 'Pakistani',
            'document_type' => 'passport',
            'passenger_type' => 'adult',
            'sort_order' => 0,
        ]);

        if ($withChildAndInfant) {
            GroupBookingPassenger::query()->create([
                'group_booking_id' => $booking->id,
                'title' => 'Master',
                'first_name' => 'Sara',
                'last_name' => 'Khan',
                'gender' => 'female',
                'date_of_birth' => '2018-05-01',
                'passport_number' => 'CD7654321',
                'passport_expiry' => '2030-01-01',
                'nationality' => 'Pakistani',
                'document_type' => 'passport',
                'passenger_type' => 'child',
                'sort_order' => 1,
            ]);
            GroupBookingPassenger::query()->create([
                'group_booking_id' => $booking->id,
                'title' => 'Infant',
                'first_name' => 'Omar',
                'last_name' => 'Khan',
                'gender' => 'male',
                'date_of_birth' => '2025-01-01',
                'passport_number' => 'EF1111111',
                'passport_expiry' => '2030-01-01',
                'nationality' => 'Pakistani',
                'document_type' => 'passport',
                'passenger_type' => 'infant',
                'sort_order' => 2,
            ]);
        }

        return $booking;
    }

    /**
     * @return array<string, mixed>
     */
    private function revalidationHttpFakes(): array
    {
        return [
            'ameer.test/api/available/airlines' => Http::response(['airlines' => []], 200),
            'ameer.test/api/group/detail/501' => Http::response([
                'group' => [
                    'id' => '501',
                    'sector' => 'SKT-SHJ',
                    'dept_date' => '2026-08-01',
                    'price' => 99000,
                    'available_no_of_pax' => 5,
                ],
            ], 200),
            'ameer.test/api/available/seats/501' => Http::response(['seats' => 5], 200),
        ];
    }
}
