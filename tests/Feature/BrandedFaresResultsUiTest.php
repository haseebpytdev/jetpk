<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Canonical public results UI is the Next.js app under frontend/.
 * Blade results/checkout views are thin shells — assert branded-fare contracts on the live frontend sources.
 */
class BrandedFaresResultsUiTest extends TestCase
{
    public function test_results_page_wires_branded_fare_option_keys_into_selection(): void
    {
        $page = file_get_contents(base_path('frontend/features/flight-results/components/FlightResultsPage.tsx'));
        $this->assertIsString($page);
        $this->assertStringContainsString('fare_option_key', $page);
        $this->assertStringContainsString('branded_fares_display_options', $page);
        $this->assertStringContainsString('fare_family_options_display', $page);
    }

    public function test_fare_family_details_renders_supplier_option_cards(): void
    {
        $src = file_get_contents(base_path('frontend/features/flight-details/components/FareFamilyDetails.tsx'));
        $this->assertIsString($src);
        $this->assertStringContainsString('option_key', $src);
        $this->assertStringContainsString('onSelect', $src);
        $this->assertStringContainsString('selectedKey', $src);

        $carousel = file_get_contents(base_path('frontend/features/flight-results/components/BrandedFareCarousel.tsx'));
        $this->assertIsString($carousel);
        $this->assertStringContainsString('Choose fare family', $carousel);
        $this->assertStringContainsString('option_key', $carousel);
        $this->assertStringContainsString('data-testid="branded-fare-carousel"', $carousel);
    }

    public function test_details_drawer_uses_fare_family_options_display(): void
    {
        $src = file_get_contents(base_path('frontend/features/flight-details/components/FlightDetailsDrawer.tsx'));
        $this->assertIsString($src);
        $this->assertStringContainsString('FareFamilyDetails', $src);
        $this->assertStringContainsString('fare_family_options_display', $src);
        $this->assertStringContainsString('branded_fares_display_options', $src);
    }

    public function test_checkout_nav_preserves_fare_option_key(): void
    {
        $api = file_get_contents(base_path('frontend/features/flight-results/services/flight-results-api.ts'));
        $this->assertIsString($api);
        $this->assertStringContainsString('fare_option_key', $api);
        $this->assertStringContainsString('fareOptionKey', $api);

        $nav = file_get_contents(base_path('frontend/features/flight-results/utils/checkout-nav.ts'));
        $this->assertIsString($nav);
        $this->assertStringContainsString('fare_option_key', $nav);
    }

    public function test_passenger_types_expose_fare_family_for_selected_offer(): void
    {
        $src = file_get_contents(base_path('frontend/features/standard-booking/types/index.ts'));
        $this->assertIsString($src);
        $this->assertStringContainsString('fare_family', $src);
    }

    public function test_booking_controller_does_not_apply_branded_snapshot_mutation(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Frontend/BookingController.php'));

        $this->assertStringNotContainsString('applyBrandedFareOptionToOfferSnapshot', $src);
    }

    public function test_booking_controller_logs_branded_fare_checkout_request_received(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Frontend/BookingController.php'));

        $this->assertStringContainsString('branded_fare_checkout_request_received', $src);
        $this->assertStringContainsString('logBrandedFareCheckoutRequestReceived', $src);
    }

    public function test_offer_selection_hook_sends_selected_fare_option_id(): void
    {
        $src = file_get_contents(base_path('frontend/features/flight-results/hooks/use-offer-selection.ts'));
        $this->assertIsString($src);
        $this->assertStringContainsString('selectedFareOptionId', $src);
        $this->assertStringContainsString('fareOptionKey', $src);
    }
}
