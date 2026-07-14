<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Support\Bookings\BookingSupplierConfirmationNoticeResolver;
use App\Support\Bookings\BookingSupplierProviderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingSupplierConfirmationNoticeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_sabre_successful_refresh_with_pnr_does_not_show_skipped_revalidation_warning(): void
    {
        $booking = $this->makeBooking(SupplierProvider::Sabre->value, [
            'revalidation_status' => 'success',
            'offer_refresh_status' => 'refreshed',
            'sabre_checkout_outcome' => [
                'success' => true,
                'live_call_attempted' => true,
                'revalidation_skipped_by_config' => true,
                'status' => 'pending_payment_or_ticketing',
                'pnr' => 'ABC123',
            ],
        ], pnr: 'ABC123');

        $notice = BookingSupplierConfirmationNoticeResolver::resolveForBooking($booking, $booking->meta['sabre_checkout_outcome']);

        $this->assertNotNull($notice);
        $this->assertSame(SupplierProvider::Sabre->value, $notice['provider']);
        $this->assertStringContainsString('successfully refreshed', strtolower($notice['notice']));
        $this->assertStringNotContainsString('without completed fare revalidation', strtolower($notice['notice']));
        $this->assertNull($notice['reason_code']);
    }

    public function test_sabre_fare_revalidated_at_suppresses_skipped_warning(): void
    {
        $booking = $this->makeBooking(SupplierProvider::Sabre->value, [
            'sabre_checkout_outcome' => [
                'success' => true,
                'live_call_attempted' => true,
                'revalidation_skipped_by_config' => true,
                'status' => 'pending_payment_or_ticketing',
            ],
        ]);
        $booking->forceFill(['fare_revalidated_at' => now()])->save();

        $notice = BookingSupplierConfirmationNoticeResolver::resolveForBooking(
            $booking->fresh(),
            $booking->meta['sabre_checkout_outcome'],
        );

        $this->assertNotNull($notice);
        $this->assertStringContainsString('successfully refreshed', strtolower($notice['notice']));
    }

    public function test_sabre_genuine_skip_shows_reason_code(): void
    {
        $outcome = [
            'success' => true,
            'live_call_attempted' => false,
            'revalidation_skipped_by_config' => true,
            'revalidation_bypass_enabled' => false,
            'status' => 'pending_payment_or_ticketing',
        ];

        $booking = $this->makeBooking(SupplierProvider::Sabre->value, []);

        $notice = BookingSupplierConfirmationNoticeResolver::resolveForBooking($booking, $outcome);

        $this->assertNotNull($notice);
        $this->assertSame('revalidation_skipped_without_refresh', $notice['reason_code']);
        $this->assertStringContainsString('revalidation_skipped_without_refresh', $notice['notice']);
    }

    public function test_pia_ndc_booking_does_not_read_sabre_session_notice(): void
    {
        $booking = $this->makeBooking(SupplierProvider::PiaNdc->value, [
            'selected_fare_family_option' => ['name' => 'SMART', 'brand_code' => 'SM'],
        ]);

        $legacySabreNotice = 'Sabre booking was attempted without completed fare revalidation because pre-booking revalidation is disabled in configuration. Ticketing remains disabled.';

        $notice = BookingSupplierConfirmationNoticeResolver::resolveForBooking($booking, null, $legacySabreNotice);

        $this->assertNull($notice);
    }

    public function test_non_sabre_booking_does_not_show_sabre_warning(): void
    {
        $booking = $this->makeBooking(SupplierProvider::Duffel->value, []);

        $notice = BookingSupplierConfirmationNoticeResolver::resolveForBooking(
            $booking,
            null,
            'Sabre booking was attempted without completed fare revalidation because pre-booking revalidation is disabled in configuration.',
        );

        $this->assertNull($notice);
    }

    public function test_sabre_smart_brand_not_overwritten_by_ecolight_context(): void
    {
        $meta = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'fare_option_key' => 'smart-key',
            'selected_fare_family_option' => [
                'option_key' => 'smart-key',
                'name' => 'SMART',
                'brand_code' => 'SM',
                'brand_name' => 'SMART',
                'baggage_summary' => '30 KG',
                'booking_class' => 'V',
                'fare_basis' => 'VOWSM',
                'price_display' => 'PKR 26,590',
            ],
            'sabre_booking_context' => [
                'brand_code' => 'LT',
                'selected_brand_code' => 'LT',
                'selected_fare_family_option' => [
                    'brand_code' => 'LT',
                    'name' => 'ECOLIGHT',
                ],
            ],
        ];

        $reconciled = BookingSupplierConfirmationNoticeResolver::reconcileSabreBrandedFareMeta($meta);

        $this->assertSame('SM', data_get($reconciled, 'sabre_booking_context.brand_code'));
        $this->assertSame('SM', data_get($reconciled, 'sabre_booking_context.selected_brand_code'));
        $this->assertSame('SMART', data_get($reconciled, 'sabre_booking_context.selected_fare_family_option.brand_name'));
        $this->assertSame('SMART', data_get($reconciled, 'selected_brand_name'));
    }

    public function test_provider_resolver_prefers_booking_supplier_then_meta(): void
    {
        $booking = $this->makeBooking(SupplierProvider::PiaNdc->value, [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'flight_offer_snapshot' => ['supplier_provider' => SupplierProvider::Duffel->value],
        ]);

        $this->assertSame(SupplierProvider::PiaNdc->value, BookingSupplierProviderResolver::provider($booking));
        $this->assertSame(BookingSupplierProviderResolver::SCOPE_PIA_NDC, BookingSupplierProviderResolver::scope($booking));
    }

    public function test_branded_fare_card_css_uses_264px_max_width(): void
    {
        $css = file_get_contents(base_path('public/css/ota-public.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('--ota-branded-fare-card-width-max: 264px', $css);
        $this->assertStringNotContainsString('--ota-branded-fare-card-width-max: 308px', $css);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function makeBooking(string $supplier, array $meta, ?string $pnr = null): Booking
    {
        $meta = array_merge(['supplier_provider' => $supplier], $meta);

        return Booking::factory()->create([
            'supplier' => $supplier,
            'pnr' => $pnr,
            'meta' => $meta,
        ]);
    }
}
