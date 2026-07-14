<?php

namespace Tests\Feature;

use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreInspectControlledPnrPayloadDigestCommandTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);
        Http::fake();
    }

    public function test_inspect_command_is_read_only_and_outputs_payload_digest(): void
    {
        $booking = $this->booking53Style($this->approvalMetaForBooking());

        Artisan::call('sabre:inspect-controlled-pnr-payload-digest', [
            '--booking' => (string) $booking->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        $this->assertStringContainsString('ticketing_attempted=false', $output);
        $this->assertStringContainsString('cancellation_attempted=false', $output);
        $this->assertStringContainsString('has_air_book=', $output);
        $this->assertStringContainsString('airbook_segment_digest=', $output);
        $this->assertStringContainsString('airprice_digest=', $output);
        $this->assertStringContainsString('context_comparison=', $output);
        $this->assertStringContainsString('airprice_validating_carrier_present=', $output);
        $this->assertStringContainsString('hard_no_fares_rbd_carrier_risk=', $output);
        $this->assertStringContainsString('warning_reasons=', $output);
        $this->assertStringContainsString('brand_match=', $output);
        $this->assertStringContainsString('post_f9i_payload_digest_clean=', $output);
        $this->assertStringContainsString('controlled_retry_after_airprice_vc_fix_available=', $output);
        $this->assertStringContainsString('controlled_retry_after_airprice_vc_fix_blockers=', $output);
        $this->assertStringNotContainsString('request_payload', $output);
        $this->assertStringNotContainsString('response_payload', $output);
        $this->assertStringNotContainsString('raw_payload', $output);
        Http::assertNothingSent();
    }

    public function test_production_requires_exact_readonly_confirm(): void
    {
        config(['app.env' => 'production']);
        $booking = $this->booking53Style($this->approvalMetaForBooking());

        Artisan::call('sabre:inspect-controlled-pnr-payload-digest', [
            '--booking' => (string) $booking->id,
        ]);
        $this->assertStringContainsString('Production requires --confirm=', Artisan::output());

        Artisan::call('sabre:inspect-controlled-pnr-payload-digest', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'READONLY-CONTROLLED-PNR-PAYLOAD-DIGEST',
        ]);
        $this->assertStringContainsString('digest_status=', Artisan::output());
        Http::assertNothingSent();
    }

    public function test_json_output_has_no_pii_or_secrets(): void
    {
        $booking = $this->booking53Style($this->approvalMetaForBooking());

        Artisan::call('sabre:inspect-controlled-pnr-payload-digest', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringNotContainsString('passport', strtolower($output));
        $this->assertStringNotContainsString('request_body', $output);
        $decoded = json_decode(trim($output), true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['live_supplier_call_attempted']);
    }
}
