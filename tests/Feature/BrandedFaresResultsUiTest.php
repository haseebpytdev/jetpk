<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Static wiring checks for branded-fare UI (partials + JS modules).
 * Results/checkout Blade wrappers only @include the partials that own the markup.
 */
class BrandedFaresResultsUiTest extends TestCase
{
    public function test_collapsed_result_card_does_not_render_branded_fare_chip_row(): void
    {
        $src = file_get_contents(resource_path('views/frontend/flights/partials/results-page.blade.php'));

        $this->assertStringContainsString('buildBrandedFaresPanelHtml', $src);
        $this->assertStringNotContainsString("'<div class=\"ota-result-branded-fares-row\">' + brandedFaresChipsHtml", $src);
    }

    public function test_expanded_details_render_professional_fare_family_cards(): void
    {
        $src = file_get_contents(public_path('js/ota-branded-fares.js'));

        $this->assertStringContainsString('ota-branded-fare-card', $src);
        $this->assertStringContainsString('buildRenderedFareOptions', $src);
        $this->assertStringContainsString('ota-branded-fare-card__price', $src);
        $this->assertStringContainsString('data-fare-option-key', $src);
    }

    public function test_checkout_passengers_has_dedicated_selected_fare_family_block(): void
    {
        $src = file_get_contents(resource_path('views/frontend/booking/partials/passenger-details-body.blade.php'));

        $this->assertStringContainsString('buildSelectedFareFamilyCheckoutView', $src);
        $this->assertStringContainsString('selectedFareFamilyCheckout', $src);
    }

    public function test_booking_controller_does_not_apply_branded_snapshot_mutation(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Frontend/BookingController.php'));

        $this->assertStringNotContainsString('applyBrandedFareOptionToOfferSnapshot', $src);
    }

    public function test_results_book_now_requires_fare_family_selection_when_selection_active(): void
    {
        $blade = file_get_contents(resource_path('views/frontend/flights/partials/results-page.blade.php'));
        $js = file_get_contents(public_path('js/ota-branded-fares.js'));

        $this->assertStringContainsString('data-book-selected-fare', $blade);
        $this->assertStringContainsString('promptFareFamilySelection', $blade);
        $this->assertStringContainsString('navigateToCheckoutWithFareKey', $js);
        $this->assertStringContainsString('promptFareFamilySelection', $js);
        $this->assertStringContainsString('data-fare-option-key', $js);
        $this->assertStringContainsString('data-offer-id', $js);
        $this->assertStringContainsString("url.searchParams.set('offer_id', offerId)", $js);
        $this->assertStringContainsString("url.searchParams.set('fare_option_key', fareOptionKey)", $js);
    }

    public function test_mobile_results_sends_offer_id_and_fare_option_key_on_select(): void
    {
        // Dedicated mobile app bundle was retired; branded-fare checkout wiring lives in ota-branded-fares.js.
        $src = file_get_contents(public_path('js/ota-branded-fares.js'));

        $this->assertStringContainsString('navigateToCheckoutWithFareKey', $src);
        $this->assertStringContainsString('data-fare-option-key', $src);
        $this->assertStringContainsString('data-offer-id', $src);
        $this->assertStringContainsString("url.searchParams.set('offer_id', offerId)", $src);
        $this->assertStringContainsString("url.searchParams.set('fare_option_key', fareOptionKey)", $src);
    }

    public function test_booking_controller_logs_branded_fare_checkout_request_received(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Frontend/BookingController.php'));

        $this->assertStringContainsString('branded_fare_checkout_request_received', $src);
        $this->assertStringContainsString('logBrandedFareCheckoutRequestReceived', $src);
    }
}
