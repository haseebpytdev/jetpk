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
                $this->assertSame('MR', $payload['passengers'][0]['title']);
                $this->assertSame('Ali', $payload['passengers'][0]['given_name']);
                $this->assertSame('Khan', $payload['passengers'][0]['surname']);

                return Http::response(['booking_id' => 'BK-9001'], 200);
            },
            'ameer.test/api/show/booking/BK-9001' => Http::response(['booking_id' => 'BK-9001', 'status' => 'confirmed'], 200),
        ]));

        $booking = $this->seedPendingBooking();
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);

        $service = app(GroupReservationService::class);
        $service->createReservation($booking);
        $booking->refresh();
        $booking->update(['status' => GroupBookingStatus::ManualPaymentPendingReview]);

        $confirmed = $service->verifyPayment($booking, $admin);

        $this->assertSame(GroupBookingStatus::Confirmed, $confirmed->status);
        $this->assertSame('BK-9001', $confirmed->supplier_reservation_id);
        $this->assertSame('BK-9001', $confirmed->meta['supplier_booking_id'] ?? null);

        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/api/create/booking'), 1);
    }

    public function test_verify_payment_retry_does_not_duplicate_create_call(): void
    {
        Http::fake(array_merge($this->revalidationHttpFakes(), [
            'ameer.test/api/create/booking' => Http::response(['booking_id' => 'BK-9002'], 200),
            'ameer.test/api/show/booking/BK-9002' => Http::response(['booking_id' => 'BK-9002'], 200),
        ]));

        $booking = $this->seedPendingBooking();
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $service = app(GroupReservationService::class);

        $service->createReservation($booking);
        $booking->refresh();
        $booking->update([
            'status' => GroupBookingStatus::ManualPaymentPendingReview,
            'meta' => ['supplier_booking_id' => 'BK-9002'],
            'supplier_reservation_id' => 'BK-9002',
        ]);

        $service->verifyPayment($booking, $admin);

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/create/booking'));
    }

    private function seedPendingBooking(): GroupBooking
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::factory()->create(['account_type' => AccountType::Customer]);

        $inventory = GroupInventory::query()->create([
            'supplier' => 'ameer_e_millat',
            'supplier_package_id' => '501',
            'public_id' => 'AEM-501',
            'title' => 'Ameer post-payment test',
            'sector' => 'SKT-SHJ',
            'total_seats' => 5,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 99000,
            'currency' => 'PKR',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $booking = GroupBooking::query()->create([
            'reference' => 'GRP-AEM-POSTPAY',
            'user_id' => $user->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::PendingPassengerDetails,
            'seat_count' => 1,
            'total_amount' => 99000,
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
