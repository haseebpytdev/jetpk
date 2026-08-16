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
use Tests\Support\AdminLegacyViewTestHelpers;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class TicketingReadinessChecklistTest extends TestCase
{
    use AdminLegacyViewTestHelpers;
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

        $this->assertLegacyBookingShowRedirect($admin, $booking);

        $html = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringContainsString('Ticketing readiness checklist', $html);
        $this->assertStringContainsString('PNR exists', $html);
        $this->assertStringContainsString('PNR itinerary synced', $html);
    }

    public function test_staff_booking_show_displays_ticketing_readiness_checklist(): void
    {
        $booking = $this->sabreBookingWithPnr('UNGKWK');
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->assertLegacyStaffBookingShowRedirect($staff, $booking);

        $html = $this->staffBookingShowHtml($staff, $booking);
        $this->assertStringContainsString('Ticketing readiness checklist', $html);
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
            'booking_reference' => 'BKG-TR-AGENT-1',
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

        $this->assertLegacyBookingShowRedirect($admin, $booking);

        $html = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringContainsString('Ready for manual ticketing review', $html);
        $this->assertStringContainsString('live API ticketing remains disabled', $html);
    }

    public function test_checklist_does_not_render_raw_supplier_secrets(): void
    {
        $booking = $this->sabreBookingWithPnr('SECRET1', [
            'supplier_payload' => ['Authorization' => 'Bearer secret-token'],
        ]);
        $admin = $this->platformAdmin();

        $this->assertLegacyBookingShowRedirect($admin, $booking);

        $html = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringNotContainsString('secret-token', $html);
        $this->assertStringNotContainsString('supplier_payload', $html);
    }

    protected function customerBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $booking = Booking::factory()->for($agency)->create([
            'customer_id' => $customer->id,
            'booking_reference' => 'BKG-TR-CUST-1',
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
            'booking_reference' => 'BKG-TR-'.strtoupper($pnr),
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
