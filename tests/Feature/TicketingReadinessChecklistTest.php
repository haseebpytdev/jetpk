<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class TicketingReadinessChecklistTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_booking_show_displays_ticketing_readiness_checklist(): void
    {
        $booking = $this->sabreBookingWithPnr('UNGKWK');
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', ['booking' => $booking, 'tab' => 'ticketing']))
            ->assertOk()
            ->assertSee('Ticketing readiness checklist', false)
            ->assertSee('PNR exists', false)
            ->assertSee('PNR itinerary synced', false);
    }

    public function test_staff_booking_show_displays_ticketing_readiness_checklist(): void
    {
        $booking = $this->sabreBookingWithPnr('UNGKWK');
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->get(route('staff.bookings.show', ['booking' => $booking, 'tab' => 'ticketing']))
            ->assertOk()
            ->assertSee('Ticketing readiness checklist', false);
    }

    public function test_customer_booking_show_hides_ticketing_readiness_checklist(): void
    {
        $booking = $this->customerBooking();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($customer)
            ->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Ticketing readiness checklist', false)
            ->assertDontSee('Ready for manual ticketing review', false);
    }

    public function test_agent_booking_show_hides_ticketing_readiness_checklist(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();
        $booking = Booking::factory()->for($agency)->create([
            'agent_id' => $agent->id,
            'pnr' => 'AGENT1',
            'meta' => ['supplier_provider' => SupplierProvider::Sabre->value],
        ]);
        BookingPassenger::factory()->for($booking)->create();
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
        ]);

        $this->actingAs($agentUser)
            ->get(route('agent.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Ticketing readiness checklist', false);
    }

    public function test_ready_booking_shows_manual_review_message_on_admin_ticketing_tab(): void
    {
        $booking = $this->readySabreBooking();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', ['booking' => $booking, 'tab' => 'ticketing']))
            ->assertOk()
            ->assertSee('Ready for manual ticketing review', false)
            ->assertSee('live API ticketing remains disabled', false);
    }

    public function test_checklist_does_not_render_raw_supplier_secrets(): void
    {
        $booking = $this->sabreBookingWithPnr('SECRET1', [
            'supplier_payload' => ['Authorization' => 'Bearer secret-token'],
        ]);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', ['booking' => $booking, 'tab' => 'ticketing']))
            ->assertOk()
            ->assertDontSee('secret-token', false)
            ->assertDontSee('supplier_payload', false);
    }

    protected function customerBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $booking = Booking::factory()->for($agency)->create([
            'customer_id' => $customer->id,
        ]);
        BookingPassenger::factory()->for($booking)->create();
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => $customer->email,
        ]);

        return $booking;
    }

    /**
     * @param  array<string, mixed>  $metaExtra
     */
    protected function sabreBookingWithPnr(string $pnr, array $metaExtra = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        return Booking::factory()->for($agency)->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => $pnr,
            'meta' => array_merge([
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ], $metaExtra),
        ]);
    }

    protected function readySabreBooking(): Booking
    {
        $booking = $this->sabreBookingWithPnr('READY1', [
            'pnr_itinerary_snapshot' => [
                'segments' => [
                    [
                        'segment_status' => 'HK',
                        'origin' => 'LHE',
                        'destination' => 'KHI',
                    ],
                ],
            ],
            'pnr_itinerary_sync' => ['status' => 'synced'],
            'customer_total' => 15000,
            'passenger_pricing' => [['type' => 'adult']],
        ]);

        $booking->update([
            'payment_status' => 'paid',
            'balance_due' => 0,
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'first_name' => 'Ready',
            'last_name' => 'Passenger',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'ready@example.test',
        ]);

        return $booking->fresh();
    }
}
