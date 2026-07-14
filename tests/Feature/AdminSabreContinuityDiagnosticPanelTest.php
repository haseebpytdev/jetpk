<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Admin\BookingManagementController;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Sabre\Diagnostics\SabreBookingContinuityAuditor;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreHostErrorClassifier;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * Sprint 11K-E — admin/staff read-only Sabre continuity + host classification visibility.
 */
class AdminSabreContinuityDiagnosticPanelTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_booking_show_displays_safe_continuity_summary_for_sabre_booking(): void
    {
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot());

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="sabre-continuity-diagnostic-panel"', false)
            ->assertSee('Sabre continuity &amp; host classification', false)
            ->assertSee('Readiness recommendation', false)
            ->assertSee('auto pnr safe', false)
            ->assertSee('Pricing context ready', false)
            ->assertSee('Continuity field status', false)
            ->assertSee('segment count', false)
            ->assertSee('present', false);
    }

    public function test_staff_booking_show_displays_continuity_panel(): void
    {
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot());
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill(['account_type' => AccountType::Staff])->save();

        $this->actingAs($staff->fresh())
            ->get(route('staff.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="sabre-continuity-diagnostic-panel"', false);
    }

    public function test_certified_route_pending_displays_as_internal_gate_not_host_rejection(): void
    {
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => [
                'status' => 'needs_review',
                'error_code' => SabreCertifiedRouteSelector::ERROR_CODE_PENDING,
            ],
        ]);

        $panel = BookingManagementController::buildSabreContinuityDiagnosticPanel($booking);
        $this->assertTrue($panel['show']);
        $familyRow = collect($panel['summary_rows'])->firstWhere('label', 'Host error family');
        $this->assertSame('certified route pending', $familyRow['value'] ?? null);
        $this->assertStringContainsString('not sabre host rejection', strtolower((string) ($familyRow['hint'] ?? '')));
        $rejectedRow = collect($panel['summary_rows'])->firstWhere('label', 'Host rejected after local continuity');
        $this->assertSame('No', $rejectedRow['value'] ?? null);
        $evidenceRow = collect($panel['summary_rows'])->firstWhere('label', 'Host rejection evidence present');
        $this->assertSame('No', $evidenceRow['value'] ?? null);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('certified route pending', false)
            ->assertSee('not Sabre host rejection', false);
    }

    public function test_persisted_no_fares_rbd_carrier_displays_host_rejection_family(): void
    {
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => $this->failedHostOutcome([
                'safe_reason_code' => SabreHostErrorClassifier::REASON_NO_FARES_RBD_CARRIER,
                'host_error_family' => SabreHostErrorClassifier::HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER,
                'source_layer' => SabreHostErrorClassifier::LAYER_AIRPRICE,
            ]),
        ]);

        $panel = BookingManagementController::buildSabreContinuityDiagnosticPanel($booking);
        $rejectedRow = collect($panel['summary_rows'])->firstWhere('label', 'Host rejected after local continuity');
        $this->assertSame('Yes', $rejectedRow['value'] ?? null);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('no fares rbd carrier', false)
            ->assertSee('blocked host rejected after local continuity', false)
            ->assertSee('Host rejected after local continuity', false);
    }

    public function test_persisted_uc_segment_status_displays_host_rejection_family(): void
    {
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => $this->failedHostOutcome([
                'safe_reason_code' => SabreHostErrorClassifier::REASON_HOST_SELL_REJECTED_UC,
                'host_error_family' => SabreHostErrorClassifier::HOST_ERROR_FAMILY_UC_SEGMENT_STATUS,
                'source_layer' => SabreHostErrorClassifier::LAYER_AIRBOOK_SELL,
            ], [
                'airline_segment_status' => 'UC',
                'halt_on_status_received' => true,
            ]),
        ]);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('uc segment status', false)
            ->assertSee('blocked host rejected after local continuity', false);
    }

    public function test_raw_payload_pii_booking_signature_and_response_error_messages_not_rendered(): void
    {
        $snapshot = $this->completeOneSegmentSnapshot();
        $snapshot['bookingSignature'] = 'SECRET_BOOKING_SIGNATURE_SHOULD_NOT_RENDER';
        $snapshot['raw_payload']['response_error_messages'] = ['PassengerName leak in raw snapshot'];

        $booking = $this->makeSabreBooking($snapshot, [
            'flight_offer_snapshot' => ['leak' => 'should-not-render-raw-flight-offer'],
            'sabre_checkout_outcome' => array_merge($this->failedHostOutcome([
                'safe_reason_code' => SabreHostErrorClassifier::REASON_HOST_SELL_REJECTED_UC,
            ]), [
                'response_error_messages' => ['STATUS CODE UC PassengerName should not appear'],
                'bookingSignature' => 'CHECKOUT_SIGNATURE_LEAK',
            ]),
        ]);

        $response = $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking));

        $response->assertOk();
        $content = (string) $response->getContent();
        $this->assertStringContainsString('data-testid="sabre-continuity-diagnostic-panel"', $content);
        preg_match('/id="sabre-continuity-diagnostic-panel"[\s\S]*?<\/div>\s*<\/div>/', $content, $panelMatch);
        $panelHtml = $panelMatch[0] ?? '';
        $this->assertNotSame('', $panelHtml);
        $this->assertStringNotContainsString('SECRET_BOOKING_SIGNATURE_SHOULD_NOT_RENDER', $panelHtml);
        $this->assertStringNotContainsString('CHECKOUT_SIGNATURE_LEAK', $panelHtml);
        $this->assertStringNotContainsString('PassengerName', $panelHtml);
        $this->assertStringNotContainsString('response_error_messages', $panelHtml);
        $this->assertStringNotContainsString('should-not-render-raw-flight-offer', $panelHtml);
        $this->assertStringNotContainsString('continuity@example.com', $panelHtml);
        $this->assertStringNotContainsString('SECRET_BOOKING_SIGNATURE_SHOULD_NOT_RENDER', $content);
        $this->assertStringNotContainsString('PassengerName', $content);
    }

    public function test_non_sabre_booking_hides_continuity_panel(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => ['supplier_provider' => SupplierProvider::Duffel->value],
        ]);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('data-testid="sabre-continuity-diagnostic-panel"', false)
            ->assertDontSee('Sabre continuity &amp; host classification', false);
    }

    public function test_viewing_booking_show_does_not_update_booking_or_call_live_sabre(): void
    {
        Http::fake();

        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot());
        $beforeStatus = $booking->status;
        $beforeUpdatedAt = $booking->updated_at?->toIso8601String();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking))
            ->assertOk();

        Http::assertNothingSent();

        $fresh = $booking->fresh();
        $this->assertSame($beforeStatus, $fresh->status);
        $this->assertSame($beforeUpdatedAt, $fresh->updated_at?->toIso8601String());
    }

    public function test_auditor_exception_shows_safe_fallback(): void
    {
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot());

        $this->app->bind(SabreBookingContinuityAuditor::class, static function () {
            return new class
            {
                public function audit(Booking $booking): array
                {
                    throw new \RuntimeException('simulated audit failure');
                }
            };
        });

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Sabre continuity diagnostic unavailable', false);
    }

    public function test_build_sabre_continuity_diagnostic_panel_maps_auditor_report(): void
    {
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => $this->failedHostOutcome([
                'safe_reason_code' => SabreHostErrorClassifier::REASON_NO_FARES_RBD_CARRIER,
                'host_error_family' => SabreHostErrorClassifier::HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER,
            ]),
        ]);

        $panel = BookingManagementController::buildSabreContinuityDiagnosticPanel($booking);

        $this->assertTrue($panel['show']);
        $this->assertFalse($panel['unavailable']);
        $summaryLabels = array_column($panel['summary_rows'], 'label');
        $this->assertContains('Final diagnostic recommendation', $summaryLabels);
        $this->assertContains('Host error family', $summaryLabels);
        $finalRow = collect($panel['summary_rows'])->firstWhere('label', 'Final diagnostic recommendation');
        $this->assertSame('blocked host rejected after local continuity', $finalRow['value'] ?? null);
        $familyRow = collect($panel['summary_rows'])->firstWhere('label', 'Host error family');
        $this->assertSame('no fares rbd carrier', $familyRow['value'] ?? null);
        $this->assertNotEmpty($panel['continuity_field_rows']);
        $this->assertNotEmpty($panel['source_present_rows']);
    }

    /**
     * @param  array<string, mixed>  $classification
     * @param  array<string, mixed>  $checkoutExtra
     * @return array<string, mixed>
     */
    protected function failedHostOutcome(array $classification, array $checkoutExtra = []): array
    {
        return array_merge([
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'live_call_attempted' => true,
            'sabre_host_classification' => array_merge([
                'safe_summary' => 'Sabre host rejected the stored offer.',
                'recommended_admin_action' => 'Re-shop/revalidate before retrying.',
                'retry_policy' => SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER,
                'manual_review_required' => true,
                'matched_signals' => [],
            ], $classification),
        ], $checkoutExtra);
    }

    /**
     * @return array<string, mixed>
     */
    protected function completeOneSegmentSnapshot(): array
    {
        return [
            'offer_id' => '11ke-offer-1',
            'supplier_offer_id' => '11ke-offer-1',
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'EK',
            'distribution_channel' => 'GDS',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-10-01T10:00:00',
                    'arrival_at' => '2026-10-01T14:00:00',
                    'carrier' => 'EK',
                    'marketing_carrier' => 'EK',
                    'operating_carrier' => 'EK',
                    'flight_number' => '615',
                    'booking_class' => 'K',
                    'fare_basis_code' => 'KLOW',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 500,
                'currency' => 'USD',
                'base_fare' => 400,
                'taxes' => 100,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                'fare_basis_codes' => ['KLOW'],
            ],
            'raw_payload' => [
                'distribution_channel' => 'GDS',
                'shop_endpoint_path' => '/v4/offers/shop',
                'sabre_shop_context' => [
                    'itinerary_ref' => '10',
                    'pricing_information_index' => 2,
                    'leg_refs' => [3],
                    'schedule_refs' => [9],
                    'validating_carrier' => 'EK',
                    'booking_classes_by_segment' => ['K'],
                    'fare_basis_codes_by_segment' => ['KLOW'],
                ],
                'sabre_booking_context' => [
                    'itinerary_reference' => '10',
                    'pricing_information_index' => 2,
                    'validating_carrier' => 'EK',
                    'booking_classes_by_segment' => ['K'],
                    'fare_basis_codes_by_segment' => ['KLOW'],
                    'segment_slice_count' => 1,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $metaExtra
     */
    protected function makeSabreBooking(array $snapshot, array $metaExtra = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $snapshot['supplier_connection_id'] = $sabreConn->id;

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => array_merge([
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'normalized_offer_snapshot' => $snapshot,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => '2026-10-01',
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                ],
            ], $metaExtra),
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'continuity@example.com',
            'phone' => '+10000000001',
            'country' => 'US',
            'address_line' => null,
            'meta' => [],
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 400,
            'taxes' => 100,
            'fees' => 0,
            'total_amount' => 500,
            'currency' => 'USD',
        ]);

        return $booking->fresh();
    }
}
