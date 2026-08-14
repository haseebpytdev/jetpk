<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Admin\BookingManagementController;
use App\Models\Agency;
use App\Models\Booking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminLegacyViewTestHelpers;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminSabreHostClassificationPanelTest extends TestCase
{
    use AdminLegacyViewTestHelpers;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_booking_show_displays_host_classification_panel_when_meta_present(): void
    {
        $booking = $this->sabreBookingWithClassification($this->validClassification());
        $admin = $this->platformAdmin();

        $html = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringContainsString('Sabre host error classification', $html);
        $this->assertStringContainsString('Host reason code', $html);
        $this->assertStringContainsString('host sell rejected uc', $html);
        $this->assertStringContainsString('Do not retry the same offer', $html);
        $this->assertStringContainsString('Advisory only', $html);
        $this->assertStringContainsString('data-testid="sabre-host-classification-panel"', $html);
    }

    public function test_admin_booking_show_hides_host_classification_panel_when_meta_missing(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'sabre_checkout_outcome' => [
                    'status' => 'needs_review',
                    'error_code' => 'sabre_booking_application_error',
                    'live_call_attempted' => true,
                ],
            ],
        ]);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking))
            ->assertRedirect();

        $html = $this->adminBookingShowHtml($this->platformAdmin(), $booking);
        $this->assertStringNotContainsString('Sabre host error classification', $html);
        $this->assertStringNotContainsString('data-testid="sabre-host-classification-panel"', $html);
    }

    public function test_build_sabre_host_classification_panel_reads_stored_meta_only(): void
    {
        $booking = $this->sabreBookingWithClassification($this->validClassification());

        $panel = BookingManagementController::buildSabreHostClassificationPanel($booking);

        $this->assertTrue($panel['show']);
        $this->assertSame(
            'Do not retry the same offer — re-shop for a fresh itinerary.',
            $panel['fields']['retry_policy_label'] ?? null
        );
        $this->assertSame('no_retry_same_offer', $panel['fields']['retry_policy'] ?? null);
        $this->assertContains('airline_segment_status:UC', $panel['signal_badges'] ?? []);
    }

    public function test_host_classification_display_redacts_forbidden_substrings(): void
    {
        $classification = array_merge($this->validClassification(), [
            'safe_summary' => 'CreatePassengerNameRecordRQ PassengerName leak',
            'recommended_admin_action' => 'Check Telephone FormOfPayment targetCity',
            'matched_signals' => [
                'token leak',
                'credentials leak',
                '4111111111111111',
                'pax@example.com',
            ],
        ]);

        $booking = $this->sabreBookingWithClassification($classification);
        $admin = $this->platformAdmin();

        $content = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringNotContainsString('CreatePassengerNameRecordRQ', $content);
        $this->assertStringNotContainsString('PassengerName', $content);
        $this->assertStringNotContainsString('FormOfPayment', $content);
        $this->assertStringNotContainsString('targetCity', $content);
        $this->assertStringNotContainsString('4111111111111111', $content);
        $this->assertStringNotContainsString('pax@example.com', $content);
        $this->assertStringContainsString('[redacted]', $content);
        $this->assertStringNotContainsString('"matched_signals"', $content);
    }

    public function test_format_sabre_host_retry_policy_advisory_maps_known_slugs(): void
    {
        $this->assertSame(
            'Do not retry until PCC/credentials are verified.',
            BookingManagementController::formatSabreHostRetryPolicyAdvisory('no_retry_until_credentials_or_pcc_checked')
        );
        $this->assertSame(
            'custom_slug (advisory code)',
            BookingManagementController::formatSabreHostRetryPolicyAdvisory('custom_slug')
        );
    }

    /**
     * @param  array<string, mixed>  $classification
     */
    protected function sabreBookingWithClassification(array $classification): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'offer_validation_status' => 'valid',
                'sabre_checkout_outcome' => [
                    'status' => 'needs_review',
                    'error_code' => 'sabre_booking_application_error',
                    'live_call_attempted' => true,
                    'sabre_host_classification' => $classification,
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validClassification(): array
    {
        return [
            'safe_reason_code' => 'host_sell_rejected_uc',
            'safe_summary' => 'Sabre could not confirm one or more requested flight segments.',
            'recommended_admin_action' => 'Re-shop/revalidate this itinerary and review availability before retrying.',
            'retry_policy' => 'no_retry_same_offer',
            'manual_review_required' => true,
            'source_layer' => 'airbook_sell',
            'matched_signals' => ['airline_segment_status:UC'],
        ];
    }
}
