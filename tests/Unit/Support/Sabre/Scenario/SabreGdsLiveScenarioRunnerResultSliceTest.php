<?php

namespace Tests\Unit\Support\Sabre\Scenario;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRunner;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class SabreGdsLiveScenarioRunnerResultSliceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_booking_result_slice_prefers_attempt_live_call_truth_over_stale_pnr_result(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();
        $connection = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre test',
            'status' => 'active',
            'is_default' => true,
            'config' => [],
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => null,
            'meta' => [],
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'http_status' => 200,
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'live_call_attempted' => true,
                'pnr_attempted' => true,
                'safe_reason_code' => 'sabre_application_incomplete_no_locator',
                'host_error_family' => 'APPLICATION_INCOMPLETE_NO_LOCATOR',
                'retry_policy' => 'no_auto_retry',
                'mixed_mapping_comparison_result' => 'match',
                'payload_preflight_status' => 'pass',
                'segment_marketing_carriers' => ['PK', 'EK'],
                'command_pricing_carriers' => ['PK', 'EK'],
                'selected_payload_style' => 'iati_like_cpnr_v2_4_gds',
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $runner = app(SabreGdsLiveScenarioRunner::class);
        $method = new ReflectionMethod(SabreGdsLiveScenarioRunner::class, 'buildBookingResultSlice');
        $method->setAccessible(true);

        $slice = $method->invoke(
            $runner,
            'run-test',
            ['trip_type' => 'return', 'preset' => 'mixed-connecting'],
            $booking->fresh(),
            [
                'live_call_attempted' => false,
                'pnr_attempted' => false,
                'payload_schema' => 'iati_like_cpnr_v2_4_gds',
            ],
            ['brand_code' => 'Y'],
            ['row' => ['segment_count' => 2, 'validating_carrier' => 'PK', 'route' => 'LHE-DXB']],
            [],
        );

        $this->assertTrue($slice['live_call_attempted']);
        $this->assertTrue($slice['pnr_attempted']);
        $this->assertSame('needs_review', $slice['attempt_status']);
        $this->assertNull($slice['pnr']);
        $this->assertSame('match', $slice['mixed_mapping_comparison_result']);
        $this->assertSame('pass', $slice['payload_preflight_status']);
        $this->assertSame('sabre_application_incomplete_no_locator', $slice['safe_reason_code']);
    }
}
