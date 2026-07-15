<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcOptionPnrService;
use App\Services\Suppliers\TicketingService;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Bookings\AdminPiaNdcOptionPnrPresenter;
use App\Support\Bookings\PiaNdcBookingProviderContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PiaNdcAdminOptionPnrTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_panel_hidden_for_non_pia_booking(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => ['supplier_provider' => 'sabre'],
        ]);

        $panel = app(AdminPiaNdcOptionPnrPresenter::class)->panel($booking);

        $this->assertFalse($panel['show']);
    }

    public function test_manual_create_is_disabled_for_pia_ndc_bookings(): void
    {
        $connection = $this->piaConnection();
        $admin = $this->platformAdmin();
        $booking = $this->unpaidPiaBrowserBooking($connection);

        $panel = app(AdminPiaNdcOptionPnrPresenter::class)->panel($booking);
        $this->assertTrue($panel['show']);
        $this->assertFalse($panel['can_create']);

        $response = $this->actingAs($admin)->post(route('admin.bookings.create-pia-ndc-option-pnr', $booking), [
            'confirm_phrase' => PiaNdcOptionPnrService::CREATE_CONFIRM_PHRASE,
            'operator_reason' => 'Manual create should be blocked in R12H.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('pia_ndc_create');
        Http::assertNothingSent();
    }

    public function test_auto_create_updates_booking_while_unpaid(): void
    {
        $connection = $this->piaConnection();
        $booking = $this->unpaidPiaBrowserBooking($connection);

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCreate_OW_res.xml')),
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $result = app(PiaNdcOptionPnrService::class)->autoCreateOptionPnrForPublicBooking($booking);

        $this->assertTrue($result['success']);
        $this->assertNull($result['customer_notice']);

        $booking->refresh();
        $this->assertSame('7UU0J3', $booking->pnr);
        $this->assertSame('7UU0J3', $booking->supplier_reference);
        $this->assertSame('option_pnr_created', $booking->supplier_booking_status);

        $this->assertDatabaseHas('supplier_booking_attempts', [
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::PiaNdc->value,
            'action' => 'auto_create_option_pnr',
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('supplier_bookings', [
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::PiaNdc->value,
            'status' => 'pending_payment_or_ticketing',
            'pnr' => '7UU0J3',
        ]);

        Http::assertSentCount(1);
    }

    public function test_missing_provider_context_auto_create_skips_supplier_call(): void
    {
        $connection = $this->piaConnection();
        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::PiaNdc->value,
            'payment_status' => 'unpaid',
            'status' => BookingStatus::PaymentPending,
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
            ],
        ]);
        BookingPassenger::factory()->create(['booking_id' => $booking->id]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'john.doe@example.test',
            'phone' => '+923001234567',
        ]);

        Http::fake();

        $result = app(PiaNdcOptionPnrService::class)->autoCreateOptionPnrForPublicBooking($booking->fresh(['passengers', 'contact']));

        $this->assertFalse($result['success']);
        $this->assertNull($result['customer_notice']);
        Http::assertNothingSent();
    }

    public function test_duplicate_creation_blocked(): void
    {
        $connection = $this->piaConnection();
        $booking = $this->unpaidPiaBrowserBooking($connection, pnr: 'EXIST1');

        Http::fake();

        $result = app(PiaNdcOptionPnrService::class)->autoCreateOptionPnrForPublicBooking($booking);

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_duplicate_active_supplier_booking_blocked(): void
    {
        $connection = $this->piaConnection();
        $booking = $this->unpaidPiaBrowserBooking($connection);

        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::PiaNdc->value,
            'status' => 'pending_payment_or_ticketing',
            'pnr' => 'ACTIVE1',
            'supplier_reference' => 'ACTIVE1',
        ]);

        $this->assertFalse(app(PiaNdcOptionPnrService::class)->canAutoCreateForPublicBooking($booking));
    }

    public function test_ticketing_remains_blocked_while_unpaid(): void
    {
        $connection = $this->piaConnection();
        $booking = $this->unpaidPiaBrowserBooking($connection, pnr: '7UU0J3', supplierReference: '7UU0J3');

        $ticketingEligible = app(TicketingService::class)->isBookingEligibleForTicketing($booking);
        $this->assertFalse($ticketingEligible);

        $supplierActions = app(AdminBookingSupplierActions::class)->build($booking, false, $ticketingEligible);
        $this->assertStringContainsString('Payment must be verified before ticketing', (string) ($supplierActions['ticketing_status_message'] ?? ''));
    }

    public function test_non_pia_supplier_remains_payment_gated_for_generic_pnr_action(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'duffel',
            'payment_status' => 'unpaid',
            'status' => BookingStatus::PaymentPending,
            'meta' => [
                'supplier_provider' => 'duffel',
                'validated_offer_snapshot' => ['offer_id' => 'off_1'],
                'offer_validation_status' => 'valid',
            ],
        ]);

        $supplierActions = app(AdminBookingSupplierActions::class)->build($booking, false, false);
        $this->assertFalse($supplierActions['can_create_pnr']);
        $this->assertStringContainsString('Payment must be verified first', (string) $supplierActions['create_pnr_reason']);
    }

    public function test_provider_context_resolver_reads_booking_meta_without_hold_session(): void
    {
        $connection = $this->piaConnection();
        $booking = $this->unpaidPiaBrowserBooking($connection);

        $resolved = app(PiaNdcBookingProviderContextResolver::class)->resolve($booking);

        $this->assertSame('booking.meta.validated_offer_snapshot', $resolved['source']);
        $this->assertSame('raw-hitit-offer-id-for-order-create', $resolved['context']['offer_ref_id'] ?? null);
    }

    private function piaConnection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::PiaNdc,
            'base_url' => 'https://example.test/cranendc/v20.1/CraneNDCService',
            'credentials' => [
                'username' => 'test-user',
                'password' => 'test-pass',
                'agency_id' => 'SELENS',
                'agency_name' => 'NDC GATEWAY',
                'owner_code' => 'PK',
                'currency' => 'PKR',
                'language_code' => 'EN',
            ],
            'is_active' => true,
        ]);
    }

    private function unpaidPiaBrowserBooking(
        SupplierConnection $connection,
        ?string $pnr = null,
        ?string $supplierReference = null,
    ): Booking {
        $providerContext = [
            'provider' => SupplierProvider::PiaNdc->value,
            'shopping_response_ref_id' => 'b00fe7be-88f0-4de3-b455-28b5aa20f767',
            'offer_ref_id' => 'raw-hitit-offer-id-for-order-create',
            'offer_item_ref_id' => 'OfferItem-13',
            'pax_ref_id' => 'ADTPax-1',
            'owner_code' => 'PK',
            'payment_time_limit' => '2099-12-31T23:59:59',
            'offer_item_refs' => [
                [
                    'offer_item_ref_id' => 'OfferItem-13',
                    'pax_ref_id' => 'ADTPax-1',
                ],
            ],
        ];

        $snapshot = [
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'supplier_connection_id' => $connection->id,
            'raw_reference' => 'raw-hitit-offer-id-for-order-create',
            'provider_context' => $providerContext,
            'raw_payload' => ['provider_context' => $providerContext],
        ];

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::PiaNdc->value,
            'payment_status' => 'unpaid',
            'status' => BookingStatus::PaymentPending,
            'pnr' => $pnr,
            'supplier_reference' => $supplierReference,
            'hold_session_id' => null,
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'confirmation_method' => 'pay_later_booking_request',
                'validated_offer_snapshot' => $snapshot,
                'flight_offer_snapshot' => $snapshot,
                'normalized_offer_snapshot' => $snapshot,
                'checkout_search_id' => 'browser-search-1',
                'checkout_offer_id' => 'pia-ndc-browser-offer-1',
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'date_of_birth' => '1990-01-01',
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'john.doe@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact']);
    }
}
