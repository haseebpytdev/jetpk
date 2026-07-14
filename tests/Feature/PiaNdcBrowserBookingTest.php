<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcOptionPnrService;
use App\Support\Bookings\AdminPiaNdcOptionPnrPresenter;
use App\Support\Bookings\AdminPiaNdcReleaseOptionPnrPresenter;
use App\Support\Bookings\PiaNdcBookingProviderContextResolver;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PiaNdcBrowserBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_pay_later_booking_has_resolvable_provider_context_from_meta_only(): void
    {
        $connection = $this->piaConnection();
        $booking = $this->browserDraftBookingWithoutHoldSession($connection);

        $resolved = app(PiaNdcBookingProviderContextResolver::class)->resolve($booking);
        $this->assertNotSame('', $resolved['source']);
        $this->assertSame('raw-hitit-offer-id-for-order-create', $resolved['context']['offer_ref_id'] ?? null);

        $panel = app(AdminPiaNdcOptionPnrPresenter::class)->panel($booking);
        $this->assertTrue($panel['show']);
        $this->assertFalse($panel['can_create']);
        $this->assertSame('booking.meta.validated_offer_snapshot', $panel['provider_context_source']);
    }

    public function test_review_submit_auto_creates_option_pnr_for_unpaid_pia_ndc_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $connection = $this->piaConnection();
        $booking = $this->browserDraftBookingWithoutHoldSession($connection);

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCreate_OW_res.xml')),
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $response = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        $response->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertSame('7UU0J3', $booking->pnr);
        $this->assertSame('option_pnr_created', $booking->supplier_booking_status);
        $this->assertSame(BookingStatus::Pending, $booking->status);

        $this->assertDatabaseHas('supplier_booking_attempts', [
            'booking_id' => $booking->id,
            'action' => 'auto_create_option_pnr',
            'status' => 'success',
        ]);

        $confirmation = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->get(route('booking.confirmation'));
        $confirmation->assertOk();
        $confirmation->assertSee('7UU0J3', false);
        $confirmation->assertSee('Option PNR created', false);
        $confirmation->assertSee('ticketing will happen after payment verification', false);

        $releasePanel = app(AdminPiaNdcReleaseOptionPnrPresenter::class)->panel($booking);
        $this->assertTrue($releasePanel['can_release']);

        Http::assertSentCount(1);
    }

    public function test_review_submit_keeps_booking_safe_when_auto_option_pnr_fails(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $connection = $this->piaConnection();
        $booking = $this->browserDraftBookingWithoutHoldSession($connection);

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelCommit_fault_500.xml')),
                500,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $response = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        $response->assertRedirect(route('booking.confirmation'));
        $response->assertSessionHas('pia_ndc_checkout_notice', PiaNdcOptionPnrService::AUTO_FAILURE_CUSTOMER_NOTICE);

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertSame(BookingStatus::Pending, $booking->status);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('failed', $meta['pia_ndc_auto_option_pnr']['status'] ?? null);

        $this->assertDatabaseHas('supplier_booking_attempts', [
            'booking_id' => $booking->id,
            'action' => 'auto_create_option_pnr',
            'status' => 'failed',
        ]);

        $confirmation = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->get(route('booking.confirmation'));
        $confirmation->assertOk();
        $confirmation->assertSee(PiaNdcOptionPnrService::AUTO_FAILURE_CUSTOMER_NOTICE, false);

        Http::assertSentCount(1);
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

    private function browserDraftBookingWithoutHoldSession(SupplierConnection $connection): Booking
    {
        $providerContext = [
            'provider' => SupplierProvider::PiaNdc->value,
            'shopping_response_ref_id' => 'b00fe7be-88f0-4de3-b455-28b5aa20f767',
            'offer_ref_id' => 'raw-hitit-offer-id-for-order-create',
            'offer_item_ref_id' => 'OfferItem-13',
            'pax_ref_id' => 'ADTPax-1',
            'owner_code' => 'PK',
            'payment_time_limit' => '2099-12-31T23:59:59',
            'fare_type_code' => 'ECO LIGHT',
            'fare_basis' => 'VNBAG',
            'rbd' => 'V',
            'offer_item_refs' => [
                ['offer_item_ref_id' => 'OfferItem-13', 'pax_ref_id' => 'ADTPax-1'],
            ],
        ];

        $snapshot = [
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'supplier_connection_id' => $connection->id,
            'fare_family' => 'ECO LIGHT',
            'raw_reference' => 'raw-hitit-offer-id-for-order-create',
            'provider_context' => $providerContext,
            'raw_payload' => ['provider_context' => $providerContext],
            'fare_breakdown' => ['supplier_total' => 24410],
            'id' => 'pia-ndc-browser-offer-rmlaj5yv',
        ];

        $booking = Booking::factory()->create([
            'booking_reference' => 'RMLAJ5YV',
            'supplier' => SupplierProvider::PiaNdc->value,
            'payment_status' => 'unpaid',
            'status' => BookingStatus::Draft,
            'submitted_at' => null,
            'supplier_hold_status' => 'not_supported',
            'hold_session_id' => null,
            'pnr' => null,
            'supplier_reference' => null,
            'supplier_booking_status' => null,
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'confirmation_method' => 'pay_later_booking_request',
                'protection_mode' => 'pay_later_booking_request',
                'offer_validation_status' => 'valid',
                'validated_offer_snapshot' => $snapshot,
                'flight_offer_snapshot' => $snapshot,
                'normalized_offer_snapshot' => $snapshot,
                'checkout_search_id' => 'browser-search-rmlaj5yv',
                'checkout_offer_id' => 'pia-ndc-browser-offer-rmlaj5yv',
                'search_criteria' => [
                    'origin' => 'ISB',
                    'destination' => 'DXB',
                    'depart_date' => now()->addDays(14)->format('Y-m-d'),
                    'trip_type' => 'one_way',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'date_of_birth' => '1990-01-01',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'john.doe@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact']);
    }
}
