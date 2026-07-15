<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Payments\PaymentTransactionService;
use App\Services\Suppliers\PiaNdc\PiaNdcOptionPnrService;
use App\Support\Bookings\PiaNdcFareFamilyPolicy;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PiaNdcAutoOptionPnrTest extends TestCase
{
    use RefreshDatabase;

    public function test_pia_ndc_cannot_select_freedom_without_provider_context(): void
    {
        $offer = $this->piaOfferSnapshot();
        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([
            [
                'name' => 'FREEDOM',
                'brand_name' => 'FREEDOM',
                'price_total' => 28590,
                'option_key' => 'freedom-key',
            ],
            [
                'name' => 'ECO LIGHT',
                'brand_name' => 'ECO LIGHT',
                'price_total' => 24410,
                'option_key' => 'eco-light-key',
            ],
        ], $offer);

        $this->assertFalse($presentation['branded_fares_selection_active']);
        $this->assertCount(1, $presentation['fare_family_options_display']);
        $this->assertSame('ECO LIGHT', $presentation['fare_family_options_display'][0]['name']);
        $this->assertTrue($presentation['fare_family_options_display'][0]['pia_ndc_provider_backed'] ?? false);
    }

    public function test_pia_ndc_reconcile_meta_preserves_unresolved_freedom_without_eco_light_fallback(): void
    {
        $offer = $this->piaOfferSnapshot();
        $meta = [
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'validated_offer_snapshot' => $offer,
            'selected_fare_family_option' => [
                'name' => 'FREEDOM',
                'displayed_price' => 28590,
            ],
            'selected_fare_total' => 28590,
            'revalidated_fare_total' => 24410,
        ];

        $reconciled = PiaNdcFareFamilyPolicy::reconcileBookingMeta($meta);

        $this->assertSame('FREEDOM', $reconciled['selected_fare_family_option']['name'] ?? null);
        $this->assertSame(28590.0, (float) ($reconciled['selected_fare_total'] ?? 0));
        $this->assertSame(24410.0, (float) ($reconciled['revalidated_fare_total'] ?? 0));
    }

    public function test_auto_option_pnr_failure_stores_rich_sanitized_diagnostics(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $connection = $this->piaConnection();
        $booking = $this->piaDraftBooking($connection, [
            'selected_fare_family_option' => [
                'name' => 'ECO LIGHT',
                'displayed_price' => 24410,
            ],
        ]);

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelCommit_fault_500.xml')),
                500,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $result = app(PiaNdcOptionPnrService::class)->autoCreateOptionPnrForPublicBooking($booking);
        $this->assertFalse($result['success']);

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'auto_create_option_pnr')
            ->latest('id')
            ->first();

        $this->assertNotNull($attempt);
        $this->assertSame('failed', $attempt->status);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame($booking->booking_reference, $summary['booking_reference'] ?? null);
        $this->assertSame('ECO LIGHT', $summary['provider_context']['fare_type_code'] ?? null);
        $this->assertTrue($summary['offer_ref_id_present'] ?? false);
        $this->assertIsInt($summary['offer_ref_id_length'] ?? null);
        $this->assertNotEmpty($summary['diagnostic_path'] ?? null);
        $this->assertFileExists($summary['diagnostic_path'].'/summary.json');
        $this->assertIsArray($attempt->request_payload);
        $this->assertIsArray($attempt->response_payload);

        Http::assertSentCount(1);
    }

    public function test_abhipay_blocked_for_pia_ndc_when_option_pnr_missing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $connection = $this->piaConnection();
        $booking = $this->piaDraftBooking($connection);
        $booking->forceFill([
            'status' => BookingStatus::Pending,
            'meta' => array_merge(is_array($booking->meta) ? $booking->meta : [], [
                'pia_ndc_auto_option_pnr' => ['status' => 'failed'],
            ]),
        ])->save();

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 24410,
            'taxes' => 0,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 24410,
            'currency' => 'PKR',
        ]);

        $service = app(PaymentTransactionService::class);
        $this->assertFalse($service->canStartAbhiPayForBooking($booking->fresh('fareBreakdown')));
        $this->assertSame(
            'Airline reservation must be created before online payment.',
            $service->abhiPayStartBlockedMessage($booking->fresh('fareBreakdown')),
        );
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

    /**
     * @return array<string, mixed>
     */
    private function piaOfferSnapshot(): array
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

        return [
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'fare_family' => 'ECO LIGHT',
            'provider_context' => $providerContext,
            'raw_payload' => ['provider_context' => $providerContext],
            'fare_breakdown' => ['supplier_total' => 24410],
            'segments' => [
                ['flight_number' => 'PK233'],
            ],
            'id' => 'pia-ndc-auto-offer',
        ];
    }

    /**
     * @param  array<string, mixed>  $metaOverrides
     */
    private function piaDraftBooking(SupplierConnection $connection, array $metaOverrides = []): Booking
    {
        $snapshot = array_merge($this->piaOfferSnapshot(), [
            'supplier_connection_id' => $connection->id,
        ]);

        $booking = Booking::factory()->create([
            'booking_reference' => 'PIAAUTO01',
            'supplier' => SupplierProvider::PiaNdc->value,
            'payment_status' => 'unpaid',
            'status' => BookingStatus::Draft,
            'pnr' => null,
            'meta' => array_merge([
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => $snapshot,
                'flight_offer_snapshot' => $snapshot,
                'search_criteria' => [
                    'origin' => 'ISB',
                    'destination' => 'DXB',
                    'depart_date' => now()->addDays(14)->format('Y-m-d'),
                ],
            ], $metaOverrides),
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
