<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Diagnostics\SabreRevalidationPayloadStyleComparator;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreRevalidationPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sprint 11K-L — launch revalidation style probe (bfm_revalidate_v1 vs bfm_revalidate_with_pricing_context).
 */
class SabreRevalidateStyleProbeCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.cert_entitlement_matrix_enabled', false);
        parent::tearDown();
    }

    public function test_command_registered(): void
    {
        Artisan::call('list');
        $this->assertStringContainsString('sabre:revalidate-style-probe', Artisan::output());
    }

    public function test_blocked_in_production_without_confirm(): void
    {
        Config::set('app.env', 'production');

        $exit = Artisan::call('sabre:revalidate-style-probe', ['--fixture' => true]);
        $out = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('CERT-REVALIDATION-STYLE-PROBE', $out);
    }

    public function test_fixture_mode_compares_both_styles_without_http(): void
    {
        Http::fake();

        $exit = Artisan::call('sabre:revalidate-style-probe', ['--fixture' => true]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('live_sabre_http=false', $out);
        $this->assertStringContainsString('bfm_revalidate_v1', $out);
        $this->assertStringContainsString('bfm_revalidate_with_pricing_context', $out);
        $this->assertStringContainsString('baseline_linkage_preserved_in_pricing_style=true', $out);
        $this->assertStringNotContainsString('iati_like_bfm_revalidate_v1', $out);
        $this->assertStringNotContainsString('OTA_AirLowFareSearchRQ', $out);
        $this->assertStringNotContainsString('PseudoCityCode', $out);
        Http::assertNothingSent();
    }

    public function test_output_is_scalar_only_no_raw_payload(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $comparator = $this->app->make(SabreRevalidationPayloadStyleComparator::class);
        $draft = $this->fixtureDraft();
        $report = $comparator->compareLaunchStylesForDraft($draft);

        foreach ((array) ($report['styles'] ?? []) as $style => $row) {
            $this->assertContains($style, SabreRevalidationPayloadStyleComparator::LAUNCH_PROBE_STYLES);
            $this->assertIsArray($row);
            foreach ($row as $key => $value) {
                $this->assertIsString($key);
                $this->assertTrue(
                    is_bool($value) || is_int($value) || is_string($value) || $value === null,
                    'probe row must be scalar: '.$style.'.'.$key,
                );
            }
            $payload = $builder->buildPayload($draft, (string) $style);
            $rawJson = json_encode($payload);
            $this->assertIsString($rawJson);
            $this->assertStringNotContainsString($rawJson, json_encode($row));
        }
    }

    public function test_pricing_context_style_preserves_baseline_and_adds_pricing_continuity(): void
    {
        $comparator = $this->app->make(SabreRevalidationPayloadStyleComparator::class);
        $report = $comparator->compareLaunchStylesForDraft($this->fixtureDraft());

        $baseline = $report['styles']['bfm_revalidate_v1'] ?? [];
        $pricing = $report['styles']['bfm_revalidate_with_pricing_context'] ?? [];

        $this->assertTrue($report['baseline_linkage_preserved_in_pricing_style']);
        $this->assertTrue($baseline['has_vendor_pref'] ?? false);
        $this->assertTrue($baseline['has_price_request_information'] ?? false);
        $this->assertTrue($pricing['pricing_context_present'] ?? false);
        $this->assertTrue($pricing['pricing_information_index_present'] ?? false);
        $adds = $report['pricing_context_adds_vs_baseline'];
        $this->assertTrue(
            in_array('pricing_context_present', $adds, true)
            || in_array('reconstructed_pricing_context', $adds, true),
            'pricing style must add pricing continuity vs baseline',
        );
        $this->assertSame('prepare_env_flip_after_cert_http_approval', $report['launch_recommendation']);
    }

    public function test_pricing_context_style_preserves_same_carrier_two_segment_linkage(): void
    {
        $comparator = $this->app->make(SabreRevalidationPayloadStyleComparator::class);
        $draft = $this->fixtureDraft();
        $draft['validating_carrier'] = 'EK';
        $draft['segments'] = [
            [
                'origin' => 'LHE',
                'destination' => 'KHI',
                'departure_at' => '2026-09-01T06:00:00',
                'arrival_at' => '2026-09-01T07:45:00',
                'carrier' => 'EK',
                'operating_airline_code' => 'EK',
                'flight_number' => '601',
                'booking_class' => 'T',
                'fare_basis_code' => 'TAAOPPK1',
            ],
            [
                'origin' => 'KHI',
                'destination' => 'DXB',
                'departure_at' => '2026-09-01T10:00:00',
                'arrival_at' => '2026-09-01T12:00:00',
                'carrier' => 'EK',
                'operating_airline_code' => 'EK',
                'flight_number' => '603',
                'booking_class' => 'T',
                'fare_basis_code' => 'TAAOPPK1',
            ],
        ];
        $draft['_sabre_shop_context']['leg_refs'] = [3, 4];
        $draft['_sabre_shop_context']['schedule_refs'] = [9, 10];

        $report = $comparator->compareLaunchStylesForDraft($draft);
        $pricing = $report['styles']['bfm_revalidate_with_pricing_context'] ?? [];

        $this->assertTrue($report['baseline_linkage_preserved_in_pricing_style']);
        $this->assertSame(2, $pricing['segment_count'] ?? null);
        $this->assertTrue($pricing['pricing_context_present'] ?? false);
        $this->assertTrue($pricing['itinerary_ref_present'] ?? false);
        $this->assertTrue($pricing['leg_refs_present'] ?? false);
        $this->assertTrue($pricing['schedule_refs_present'] ?? false);
        $this->assertTrue($pricing['booking_classes_present'] ?? false);
        $this->assertTrue($pricing['fare_basis_present'] ?? false);
        $this->assertTrue($pricing['validating_carrier_present'] ?? false);
        $this->assertTrue($pricing['currency_present'] ?? false);
    }

    public function test_native_client_gds_style_is_not_part_of_launch_probe_or_default(): void
    {
        $this->assertSame('bfm_revalidate_v1', config('suppliers.sabre.revalidate_payload_style'));
        $this->assertNotContains(
            'client_gds_revalidate_v1',
            SabreRevalidationPayloadStyleComparator::LAUNCH_PROBE_STYLES,
        );

        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->fixtureDraft(), 'client_gds_revalidate_v1');

        $this->assertArrayHasKey('RevalidateItineraryRQ', $payload);
        $this->assertArrayNotHasKey('OTA_AirLowFareSearchRQ', $payload);
        $this->assertSame('client_gds_revalidate_v1', $payload['_ota_revalidate_payload_style'] ?? null);
    }

    public function test_config_default_remains_bfm_revalidate_v1(): void
    {
        $this->assertSame('bfm_revalidate_v1', config('suppliers.sabre.revalidate_payload_style'));

        Artisan::call('sabre:revalidate-style-probe', ['--fixture' => true]);
        $this->assertStringContainsString('production_default_unchanged=true', Artisan::output());
    }

    public function test_send_invokes_revalidate_only_not_pnr_or_ticketing(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection();

        $this->partialMock(SabreBookingService::class, function ($mock): void {
            $mock->shouldReceive('runRevalidationBeforeBooking')
                ->twice()
                ->andReturn([
                    'success' => true,
                    'http_status' => 200,
                    'reason_code' => 'sabre_revalidation_success',
                    'error_digest' => ['response_error_codes' => [], 'response_error_messages' => []],
                ]);
            $mock->shouldNotReceive('createBooking');
            $mock->shouldNotReceive('submitBooking');
        });

        $exit = Artisan::call('sabre:revalidate-style-probe', [
            '--fixture' => true,
            '--send' => true,
            '--connection' => (string) $conn->id,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('live_sabre_http=true', $out);
        $this->assertStringContainsString('pnr_create_attempted=false', $out);
        $this->assertStringContainsString('reval_success', $out);
        $this->assertStringContainsString('true', $out);
    }

    protected function seedSabreConnection(string $baseUrl = 'https://api.cert.platform.sabre.com'): SupplierConnection
    {
        $agency = Agency::factory()->create();
        $conn = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'base_url' => $baseUrl,
            'is_active' => true,
        ]);
        Config::set('suppliers.sabre.default_base_url', $baseUrl);

        return $conn;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fixtureDraft(): array
    {
        return [
            'provider' => SupplierProvider::Sabre->value,
            'selected_offer_id' => '11kl-fixture-offer',
            'supplier_offer_id' => '11kl-fixture-offer',
            'validating_carrier' => 'EK',
            'fare' => ['amount' => 450.0, 'currency' => 'USD'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-09-01T10:00:00',
                    'arrival_at' => '2026-09-01T14:00:00',
                    'carrier' => 'EK',
                    'operating_airline_code' => 'EK',
                    'flight_number' => '615',
                    'booking_class' => 'T',
                    'fare_basis_code' => 'TAAOPPK1',
                ],
            ],
            'passengers' => [['type' => 'ADT']],
            '_sabre_shop_context' => [
                'itinerary_group_index' => 1,
                'itinerary_ref' => '10',
                'pricing_information_index' => 2,
                'leg_refs' => [3],
                'schedule_refs' => [9],
                'fare_component_refs' => [7],
                'pricing_information_ref' => 'pi-2',
            ],
            '_sabre_shop_identifiers' => [
                'pseudo_city_code' => 'TEST',
            ],
        ];
    }
}
