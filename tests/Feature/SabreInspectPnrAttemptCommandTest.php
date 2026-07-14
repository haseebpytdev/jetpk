<?php

namespace Tests\Feature;

use App\Console\Commands\SabreInspectPnrAttemptCommand;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Sabre\SabrePnrAttemptReadOnlyDiagnostics;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreInspectPnrAttemptCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_read_only_pnr_attempt_diagnostic_shows_safe_pointer_and_message(): void
    {
        $booking = Booking::query()->create([
            'agency_id' => Agency::query()->value('id'),
            'booking_reference' => 'BK-V25-ATTEMPT',
            'status' => 'pending',
            'supplier_booking_status' => 'failed',
        ]);

        $attempt = SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => 2,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'sabre_booking_validation_failed',
            'error_message' => 'Sabre booking validation failed: instance type (object)',
            'safe_summary' => [
                'live_call_attempted' => true,
                'http_status' => 400,
                'endpoint_path' => '/v2.5.0/passenger/records?mode=create',
                'payload_schema' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V2_5_GDS,
                'selected_payload_style' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V2_5_GDS,
                'cpnr_schema_validation_pointer' => '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers/CommandPricing',
                'safe_validation_excerpts_structured' => [[
                    'pointer' => '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers/CommandPricing',
                    'message_excerpt' => 'instance type (object) is not allowed',
                    'error_type' => 'schema_validation_failed',
                ]],
                'v25_airprice_pricing_qualifiers_digest' => [
                    'pricing_qualifiers_present' => true,
                    'command_pricing_shape' => 'array',
                    'brand_qualifier_shape' => 'array',
                ],
                'ticket_issuance_attempted' => false,
                'airticket_attempted' => false,
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $this->artisan('sabre:inspect-pnr-attempt', [
            '--attempt' => (string) $attempt->id,
            '--confirm' => SabreInspectPnrAttemptCommand::CONFIRM_PHRASE,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('attempt_id='.$attempt->id)
            ->expectsOutputToContain('error_code="sabre_booking_validation_failed"')
            ->expectsOutputToContain('CommandPricing');

        $summary = app(SabrePnrAttemptReadOnlyDiagnostics::class)->summarizeAttempt($attempt->fresh());
        $output = json_encode($summary);
        $this->assertIsString($output);
        $this->assertStringNotContainsString('passport', strtolower($output));
        $this->assertStringNotContainsString('booker@', strtolower($output));
        $this->assertStringContainsString('CommandPricing', (string) ($summary['cpnr_schema_validation_pointer'] ?? ''));
    }
}
