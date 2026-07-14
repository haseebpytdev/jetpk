<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Support\Bookings\BookingSupplierConfirmationNoticeResolver;
use App\Support\Bookings\SupplierLifecycleContextResolver;
use App\Support\Bookings\SupplierLifecycleRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierLifecycleRouterTest extends TestCase
{
    use RefreshDatabase;

    public function test_sabre_gds_booking_resolves_gds_handler(): void
    {
        $booking = $this->booking(SupplierProvider::Sabre->value, [
            'distribution_channel' => 'gds',
            'revalidation_status' => 'success',
        ]);

        $ctx = app(SupplierLifecycleContextResolver::class)->resolve($booking);

        $this->assertSame(SupplierLifecycleContextResolver::HANDLER_SABRE_GDS, $ctx['handler_key']);
        $this->assertSame('gds', $ctx['supplier_channel']);
        $this->assertTrue($ctx['supports_pnr_or_order']);
    }

    public function test_sabre_ndc_booking_does_not_use_gds_handler(): void
    {
        $booking = $this->booking(SupplierProvider::Sabre->value, [
            'distribution_channel' => 'ndc',
            'sabre_checkout_outcome' => ['success' => true, 'status' => 'created'],
        ]);

        $ctx = app(SupplierLifecycleContextResolver::class)->resolve($booking);

        $this->assertSame(SupplierLifecycleContextResolver::HANDLER_SABRE_NDC, $ctx['handler_key']);
        $this->assertFalse($ctx['supports_ticketing']);
        $this->assertFalse($ctx['supports_cancellation']);
        $this->assertFalse(app(SupplierLifecycleContextResolver::class)->isHandler(
            $booking,
            SupplierLifecycleContextResolver::HANDLER_SABRE_GDS,
        ));
    }

    public function test_sabre_gds_does_not_read_pia_ndc_fields_for_notice(): void
    {
        $booking = $this->booking(SupplierProvider::Sabre->value, [
            'distribution_channel' => 'gds',
            'pia_ndc_context' => ['order_id' => 'PIA-123'],
            'pia_ndc_auto_option_pnr' => ['status' => 'failed'],
            'sabre_checkout_outcome' => [
                'success' => true,
                'live_call_attempted' => true,
                'revalidation_skipped_by_config' => true,
                'status' => 'pending_payment_or_ticketing',
                'pnr' => 'ABC123',
            ],
            'revalidation_status' => 'success',
        ], pnr: 'ABC123');

        $notice = app(SupplierLifecycleRouter::class)->confirmationNotice($booking, $booking->meta['sabre_checkout_outcome']);

        $this->assertNotNull($notice);
        $this->assertSame(SupplierLifecycleContextResolver::HANDLER_SABRE_GDS, $notice['handler_key']);
        $this->assertStringNotContainsString('pia', strtolower($notice['notice']));
    }

    public function test_sabre_ndc_booking_does_not_emit_gds_revalidation_warning(): void
    {
        $booking = $this->booking(SupplierProvider::Sabre->value, [
            'distribution_channel' => 'ndc',
        ]);

        $legacyGdsWarning = 'Sabre booking was attempted without completed fare revalidation because pre-booking revalidation is disabled in configuration.';

        $notice = app(SupplierLifecycleRouter::class)->confirmationNotice($booking, null, $legacyGdsWarning);

        $this->assertNull($notice);
        $this->assertFalse(BookingSupplierConfirmationNoticeResolver::sabreHasRevalidationSuccessIndicators($booking));
    }

    public function test_pia_ndc_booking_does_not_show_sabre_gds_revalidation_warning(): void
    {
        $booking = $this->booking(SupplierProvider::PiaNdc->value, [
            'selected_fare_family_option' => ['name' => 'SMART', 'brand_code' => 'SM'],
        ]);

        $legacySabreNotice = 'Sabre booking was attempted without completed fare revalidation because pre-booking revalidation is disabled in configuration. Ticketing remains disabled.';

        $notice = app(SupplierLifecycleRouter::class)->confirmationNotice($booking, null, $legacySabreNotice);

        $this->assertNull($notice);
        $this->assertSame(SupplierLifecycleContextResolver::HANDLER_PIA_NDC, app(SupplierLifecycleContextResolver::class)->handlerKey($booking));
    }

    public function test_duffel_booking_does_not_enter_sabre_gds_handler(): void
    {
        $booking = $this->booking(SupplierProvider::Duffel->value, [
            'sabre_checkout_outcome' => ['success' => true],
            'sabre_booking_context' => ['brand_code' => 'SM'],
        ]);

        $ctx = app(SupplierLifecycleContextResolver::class)->resolve($booking);

        $this->assertSame(SupplierLifecycleContextResolver::HANDLER_DUFFEL, $ctx['handler_key']);
        $this->assertNull(app(SupplierLifecycleRouter::class)->confirmationNotice(
            $booking,
            null,
            'Sabre booking was attempted without completed fare revalidation because pre-booking revalidation is disabled in configuration.',
        ));
    }

    public function test_airblue_booking_does_not_enter_sabre_lifecycle_handler(): void
    {
        $booking = $this->booking(SupplierProvider::Airblue->value, []);

        $this->assertSame(
            SupplierLifecycleContextResolver::HANDLER_AIRBLUE,
            app(SupplierLifecycleContextResolver::class)->handlerKey($booking),
        );
        $this->assertFalse(app(SupplierLifecycleContextResolver::class)->isHandler(
            $booking,
            SupplierLifecycleContextResolver::HANDLER_SABRE_GDS,
        ));
    }

    public function test_selected_fare_context_uses_selected_option_not_cheapest_snapshot(): void
    {
        $booking = $this->booking(SupplierProvider::Sabre->value, [
            'distribution_channel' => 'gds',
            'fare_option_key' => 'smart-key',
            'selected_fare_family_option' => [
                'option_key' => 'smart-key',
                'name' => 'SMART',
                'brand_code' => 'SM',
                'booking_class' => 'V',
            ],
            'normalized_offer_snapshot' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'selected_fare_family_option' => [
                    'option_key' => 'eco-key',
                    'name' => 'ECOLIGHT',
                    'brand_code' => 'LT',
                ],
            ],
        ]);

        $fareContext = app(SupplierLifecycleContextResolver::class)->resolve($booking)['selected_fare_context'];

        $this->assertSame('smart-key', $fareContext['fare_option_key']);
        $this->assertSame('SM', $fareContext['brand_code']);
        $this->assertSame('fare_option_key', $fareContext['selection_source']);
    }

    public function test_generic_confirmation_resolver_delegates_to_supplier_router(): void
    {
        $booking = $this->booking(SupplierProvider::PiaNdc->value, [
            'pia_ndc_auto_option_pnr' => ['status' => 'failed'],
        ]);

        $notice = BookingSupplierConfirmationNoticeResolver::resolveForBooking($booking);

        $this->assertNotNull($notice);
        $this->assertSame(SupplierLifecycleContextResolver::HANDLER_PIA_NDC, $notice['handler_key']);
    }

    public function test_missing_handler_returns_unsupported_manual_state_not_exception(): void
    {
        $booking = $this->booking('unknown_supplier_xyz', []);
        $ctx = app(SupplierLifecycleContextResolver::class)->resolve($booking);

        $state = app(SupplierLifecycleRouter::class)->unsupportedManualState($ctx, 'ticketing');

        $this->assertFalse($state['supported']);
        $this->assertSame('manual_action_required', $state['state']);
        $this->assertStringContainsString('not automated', strtolower($state['message']));
    }

    public function test_group_booking_resolves_group_handler(): void
    {
        $booking = $this->booking('group', ['source_channel' => 'group_ticketing']);

        $ctx = app(SupplierLifecycleContextResolver::class)->resolve($booking);

        $this->assertSame(SupplierLifecycleContextResolver::HANDLER_GROUP, $ctx['handler_key']);
        $this->assertFalse($ctx['supports_pnr_or_order']);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function booking(string $supplier, array $meta, ?string $pnr = null): Booking
    {
        $meta = array_merge(['supplier_provider' => $supplier], $meta);

        return Booking::factory()->create([
            'supplier' => $supplier,
            'pnr' => $pnr,
            'meta' => $meta,
        ]);
    }
}
