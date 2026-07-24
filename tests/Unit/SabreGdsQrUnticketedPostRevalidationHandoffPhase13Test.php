<?php

namespace Tests\Unit;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Gds\SabreGdsRevalidationService;
use App\Support\Sabre\Scenario\SabreGdsAuthoritativeRevalidatedBookingContext;
use App\Support\Sabre\Scenario\SabreGdsAuthoritativeRevalidatedBookingContextBuilder;
use App\Support\Sabre\Scenario\SabreGdsPrivateLifecycleArtifactWriter;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedBookAndRetrieveLifecycle;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedBookAndRetrieveRevalidationHandoff;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedPostRevalidationFinalOfferValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SabreGdsQrUnticketedPostRevalidationHandoffPhase13Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['suppliers.sabre.ticketing_enabled' => false]);
    }

    public function test_resolve_offer_from_booking_uses_normalized_offer_snapshot(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'normalized_offer_snapshot' => $this->minimalSabreOffer(),
            ],
        ]);
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre->value]);

        $service = app(SabreGdsRevalidationService::class);
        $method = new \ReflectionMethod($service, 'resolveOfferFromBooking');
        $method->setAccessible(true);
        $offer = $method->invoke($service, $booking);

        $this->assertSame('OFFER-1', $offer['supplier_offer_id'] ?? null);
    }

    public function test_empty_legacy_offer_snapshot_path_fails_offer_validation_like_production(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'normalized_offer_snapshot' => $this->minimalSabreOffer(),
                'sabre_booking_context' => [],
            ],
        ]);
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre->value]);

        $outcome = app(SabreGdsRevalidationService::class)->revalidateForBooking($booking, $connection, false);

        $this->assertNotSame('offer_validation_failed', $outcome['reason_code'] ?? null);
    }

    public function test_production_shaped_revalidation_evidence_passes_final_offer_validation(): void
    {
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre->value]);
        $evidence = $this->productionShapedRevalidationEvidence();
        $snap = $this->minimalSabreOffer();
        $continuity = [
            'safe_offer_fingerprint' => 'fp-selected',
            'segment_signature' => 'seg-sig',
            'currency' => 'USD',
            'source_identifier_hash' => hash('sha256', 'src'),
        ];
        $passengerBundle = $this->passengerBundle();

        $context = app(SabreGdsAuthoritativeRevalidatedBookingContextBuilder::class)->build(
            $connection,
            $snap,
            $evidence,
            $continuity,
            $passengerBundle,
            '5a7f446f-a5c2-424c-9ead-af2968f12fa7',
        );

        $result = app(SabreGdsQrUnticketedPostRevalidationFinalOfferValidator::class)->validate(
            $context,
            $evidence,
            $passengerBundle,
        );

        $this->assertTrue($result['final_offer_validation_success'] ?? false);
        $this->assertTrue($result['pre_create_gate_complete'] ?? false);
    }

    public function test_stale_empty_snapshot_blocks_before_authoritative_context(): void
    {
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre->value]);
        $evidence = $this->productionShapedRevalidationEvidence();
        $snap = $this->minimalSabreOffer();
        $snap['segments'] = [];
        $context = app(SabreGdsAuthoritativeRevalidatedBookingContextBuilder::class)->build(
            $connection,
            $snap,
            $evidence,
            ['currency' => 'USD'],
            $this->passengerBundle(),
        );

        $result = app(SabreGdsQrUnticketedPostRevalidationFinalOfferValidator::class)->validate(
            $context,
            $evidence,
            $this->passengerBundle(),
        );

        $this->assertFalse($result['final_offer_validation_success'] ?? true);
        $this->assertSame('segment_count_mismatch', $result['final_offer_validation_reason_code'] ?? null);
    }

    public function test_plan_artifact_is_mode_0600_at_creation(): void
    {
        $writer = app(SabreGdsPrivateLifecycleArtifactWriter::class);
        $written = $writer->write('sabre-gds-qr-unticketed-book-and-retrieve/test-plan.json', [
            'probe_mode' => 'plan',
        ]);

        $this->assertSame(0600, $written['mode_expected']);
        $this->assertFileExists($written['absolute_path']);
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame(0600, $written['mode_actual']);
        }
    }

    public function test_plan_mode_does_not_create_booking_rows(): void
    {
        $before = Booking::query()->count();
        Artisan::call('sabre:gds-qr-unticketed-book-and-retrieve', [
            '--departure-date' => '2026-09-05',
            '--passenger-json' => $this->privatePassengerFixturePath(),
            '--plan' => true,
        ]);
        $this->assertSame($before, Booking::query()->count());
    }

    public function test_fezjfp_reference_is_refused(): void
    {
        $lifecycle = app(SabreGdsQrUnticketedBookAndRetrieveLifecycle::class);
        $this->assertTrue($lifecycle->containsDeniedLocator(['notes' => 'FEZJFP']));
    }

    public function test_handoff_still_requires_unique_usable_linkage(): void
    {
        $handoff = app(SabreGdsQrUnticketedBookAndRetrieveRevalidationHandoff::class);
        $this->assertFalse($handoff->allowsPnrCreate([
            'revalidation_success' => true,
            'freshness_satisfied' => true,
            'revalidation_diagnostics' => [
                'unique_usable_linkage_match_count' => 0,
                'ambiguous_linkage_match_count' => 0,
                'pricing_complete' => true,
                'fare_basis_complete' => true,
                'usable_fare_linkage' => true,
            ],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function productionShapedRevalidationEvidence(): array
    {
        return [
            'revalidation_success' => true,
            'freshness_satisfied' => true,
            'revalidated_total' => 540.73,
            'revalidated_currency' => 'USD',
            'selected_total' => 540.73,
            'selected_currency' => 'USD',
            'revalidation_at' => '2026-07-22T10:00:00+00:00',
            'revalidation_correlation_id' => '5a7f446f-a5c2-424c-9ead-af2968f12fa7',
            'revalidation_diagnostics' => [
                'unique_usable_linkage_match_count' => 1,
                'ambiguous_linkage_match_count' => 0,
                'pricing_complete' => true,
                'fare_basis_complete' => true,
                'usable_fare_linkage' => true,
                'selected_response_candidate_ordinal' => 1,
            ],
            'canonical_linkage_normalization' => [
                'selected_draft_signature_equal' => true,
                'selected_segment_count' => 2,
                'draft_segment_count' => 2,
                'draft_segment_component_summaries' => [
                    ['booking_class' => 'N', 'fare_basis_code' => 'NLR5R1RI'],
                    ['booking_class' => 'N', 'fare_basis_code' => 'NLR5R1RI'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalSabreOffer(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_offer_id' => 'OFFER-1',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'booking_class' => 'N',
                    'fare_basis_code' => 'NLR5R1RI',
                    'departure_at' => '2026-09-05T08:00:00',
                    'arrival_at' => '2026-09-05T10:00:00',
                    'carrier' => 'QR',
                    'flight_number' => '633',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'JED',
                    'booking_class' => 'N',
                    'fare_basis_code' => 'NLR5R1RI',
                    'departure_at' => '2026-09-05T12:00:00',
                    'arrival_at' => '2026-09-05T14:00:00',
                    'carrier' => 'QR',
                    'flight_number' => '1178',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 540.73,
                'currency' => 'USD',
                'passenger_counts' => ['adults' => 1],
            ],
            'final_customer_price' => 540.73,
            'pricing_currency' => 'USD',
            'adults' => 1,
        ];
    }

    /**
     * @return array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>
     * }
     */
    private function passengerBundle(): array
    {
        return [
            'passenger' => [
                'first_name' => 'Test',
                'last_name' => 'Passenger',
                'passenger_type' => 'adult',
            ],
            'contact' => [
                'email' => 'phase13-test@example.invalid',
                'phone' => '+920000000000',
                'country' => 'PK',
            ],
        ];
    }

    private function privatePassengerFixturePath(): string
    {
        $relative = 'private/sabre/private-passenger-phase13-test.json';
        $absolute = storage_path('app/'.$relative);
        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0700, true);
        }
        file_put_contents($absolute, json_encode([
            'title' => 'MR',
            'given_name' => 'Test',
            'surname' => 'Passenger',
            'gender' => 'M',
            'dob' => '1990-01-01',
            'nationality' => 'PK',
            'country' => 'PK',
            'passport_number' => 'AB1234567',
            'passport_issue_date' => '2020-01-01',
            'passport_expiry_date' => '2030-01-01',
            'phone' => '+920000000000',
            'email' => 'phase13-test@example.invalid',
        ], JSON_THROW_ON_ERROR));
        @chmod($absolute, 0600);

        return $absolute;
    }
}
