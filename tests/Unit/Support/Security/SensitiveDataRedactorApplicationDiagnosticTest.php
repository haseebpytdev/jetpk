<?php

namespace Tests\Unit\Support\Security;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Enums\SupplierProvider;
use App\Support\Security\SensitiveDataRedactor;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensitiveDataRedactorApplicationDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitize_supplier_summary_preserves_structured_application_warning_rows(): void
    {
        $summary = SensitiveDataRedactor::sanitizeSupplierSummary([
            'safe_application_warnings' => [
                [
                    'type' => 'warning',
                    'code' => 'WARN.SWS.CLIENT.VALIDATION_FAILED',
                    'message' => 'EnhancedAirBookRQ: CommandPricing@RPH must be combined with SegmentSelect@RPH',
                ],
            ],
        ]);

        $warning = is_array($summary['safe_application_warnings'][0] ?? null) ? $summary['safe_application_warnings'][0] : [];
        $this->assertSame('WARN.SWS.CLIENT.VALIDATION_FAILED', $warning['code'] ?? null);
        $this->assertStringContainsString('SegmentSelect@RPH', (string) ($warning['message'] ?? ''));
        $this->assertNotSame('[redacted]', $warning['message'] ?? null);
    }

    public function test_supplier_booking_attempt_save_preserves_safe_application_warning_rows(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create(['agency_id' => $agency->id]);

        $attempt = SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'safe_summary' => [
                'safe_application_warnings' => [
                    [
                        'type' => 'warning',
                        'code' => 'WARN.SWS.CLIENT.VALIDATION_FAILED',
                        'message' => 'EnhancedAirBookRQ: Unable to sell segment for requested itinerary',
                    ],
                ],
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $saved = is_array($attempt->fresh()->safe_summary) ? $attempt->fresh()->safe_summary : [];
        $warning = is_array($saved['safe_application_warnings'][0] ?? null) ? $saved['safe_application_warnings'][0] : [];
        $this->assertSame('WARN.SWS.CLIENT.VALIDATION_FAILED', $warning['code'] ?? null);
        $this->assertStringContainsString('Unable to sell segment', (string) ($warning['message'] ?? ''));
    }
}
