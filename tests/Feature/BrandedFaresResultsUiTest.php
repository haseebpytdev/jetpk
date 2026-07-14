<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandedFaresResultsUiTest extends TestCase
{
    public function test_collapsed_result_card_does_not_render_branded_fare_chip_row(): void
    {
        $src = file_get_contents(resource_path('views/frontend/flights/results.blade.php'));

        $this->assertStringContainsString("var brandedFaresRowHtml = '';", $src);
        $this->assertStringNotContainsString("'<div class=\"ota-result-branded-fares-row\">' + brandedFaresChipsHtml", $src);
    }

    public function test_expanded_details_render_professional_fare_family_cards(): void
    {
        $src = file_get_contents(resource_path('views/frontend/flights/results.blade.php'));

        $this->assertStringContainsString('Fare family options', $src);
        $this->assertStringContainsString('ota-branded-fare-options__note', $src);
        $this->assertStringContainsString('Final availability, fare family and price will be confirmed during airline price validation.', $src);
        $this->assertStringContainsString('ota-flight-detail-fare-options', $src);
        $this->assertStringContainsString('ota-fare-option-card__detail-row', $src);
        $this->assertStringContainsString('ota-fare-option-card__price-block', $src);
        $this->assertStringContainsString('ota-fare-option-card__check', $src);
    }

    public function test_checkout_passengers_has_dedicated_selected_fare_family_block(): void
    {
        $src = file_get_contents(resource_path('views/frontend/booking/passenger-details.blade.php'));

        $this->assertStringContainsString('ota-checkout-selected-fare-family', $src);
        $this->assertStringContainsString('Selected fare family', $src);
        $this->assertStringContainsString('buildSelectedFareFamilyCheckoutView', $src);
    }

    public function test_booking_controller_does_not_apply_branded_snapshot_mutation(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Frontend/BookingController.php'));

        $this->assertStringNotContainsString('applyBrandedFareOptionToOfferSnapshot', $src);
    }

    public function test_results_book_now_requires_fare_family_selection_when_selection_active(): void
    {
        $src = file_get_contents(resource_path('views/frontend/flights/results.blade.php'));

        $this->assertStringContainsString('data-book-selected-fare', $src);
        $this->assertStringContainsString('Book selected fare', $src);
        $this->assertStringContainsString('promptFareFamilySelection', $src);
        $this->assertStringContainsString('navigateToCheckoutWithFareKey', $src);
        $this->assertStringContainsString('offerRequiresFareFamilySelection', $src);
        $this->assertStringContainsString('data-fare-option-key', $src);
        $this->assertStringContainsString('data-offer-id', $src);
        $this->assertStringContainsString("url.searchParams.set('offer_id', offerId)", $src);
        $this->assertStringContainsString("url.searchParams.set('fare_option_key', fareOptionKey)", $src);
    }

    public function test_mobile_results_sends_offer_id_and_fare_option_key_on_select(): void
    {
        $src = file_get_contents(public_path('js/ota-mobile-app.js'));

        $this->assertStringContainsString('navigateToCheckoutWithFareKey', $src);
        $this->assertStringContainsString('data-fare-option-key', $src);
        $this->assertStringContainsString('data-offer-id', $src);
        $this->assertStringContainsString('buildMobileFareFamilyPickerHtml', $src);
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
