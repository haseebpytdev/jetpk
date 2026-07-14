<?php

namespace Tests\Feature;

use App\Console\Commands\SabreControlledPnrContextCommand;
use App\Support\Bookings\SabreControlledPnrReadiness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreControlledPnrContextCommandTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_production_requires_confirm_phrase(): void
    {
        config(['app.env' => 'production']);
        $booking = $this->booking53Style();

        $exit = Artisan::call('sabre:controlled-pnr-context', [
            '--booking' => (string) $booking->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString(SabreControlledPnrContextCommand::PRODUCTION_READONLY_CONFIRM_PHRASE, Artisan::output());
    }

    public function test_command_outputs_redacted_safe_fields_only(): void
    {
        $booking = $this->booking53Style();

        $exit = Artisan::call('sabre:controlled-pnr-context', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $payload = json_decode($output, true);
        $this->assertIsArray($payload);
        $this->assertFalse($payload['live_supplier_call_attempted']);
        $this->assertFalse($payload['pnr_create_attempted']);
        $this->assertTrue($payload['has_usable_controlled_pnr_context']);
        $this->assertTrue($payload['raw_payload_present'] === true || $payload['raw_payload_present'] === false);
        $this->assertStringNotContainsString('passport', strtolower($output));
        $this->assertStringNotContainsString('access_token', strtolower($output));
        $this->assertStringNotContainsString('guest@example.test', $output);
    }

    public function test_readiness_output_never_contains_raw_payload_or_pii(): void
    {
        config(['suppliers.sabre.booking_live_call_enabled' => true]);
        $booking = $this->booking53Style();

        Artisan::call('sabre:controlled-pnr-readiness', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $output = Artisan::output();
        $payload = json_decode($output, true);
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('raw_payload', $payload);
        $this->assertStringNotContainsString('passport', strtolower($output));
        $this->assertStringNotContainsString('guest@example.test', $output);
    }

    public function test_create_without_confirm_reports_admin_confirmation_and_no_supplier_call(): void
    {
        config(['suppliers.sabre.booking_live_call_enabled' => true]);
        $booking = $this->booking53Style();

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'CREATE-PNR-FOR-BOOKING-'.$booking->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);

        $readiness = app(SabreControlledPnrReadiness::class)->evaluate($booking, [
            'context' => 'create_command',
            'require_admin_confirmation' => true,
            'admin_confirmation_provided' => false,
        ]);
        $this->assertFalse($readiness['eligible']);
        $this->assertContains('admin_confirmation_required', $readiness['blockers']);
        $this->assertSame('admin_confirmation_required', $readiness['reason_code']);
    }
}
