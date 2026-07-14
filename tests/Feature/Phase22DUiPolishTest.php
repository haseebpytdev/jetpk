<?php

namespace Tests\Feature;

use Tests\TestCase;

class Phase22DUiPolishTest extends TestCase
{
    public function test_homepage_does_not_show_corridor_fare_cards_heading(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Search your route to view available fares', false)
            ->assertDontSee('Available fares on your corridor', false);
    }

    public function test_results_blade_embeds_book_now_and_flight_details_in_card_template(): void
    {
        $path = resource_path('views/frontend/flights/results.blade.php');
        $src = file_get_contents($path);
        $this->assertStringContainsString('Book Now', $src);
        $this->assertStringContainsString('Flight details', $src);
        $this->assertStringContainsString('data-flight-details-open', $src);
        $this->assertStringContainsString('data-fare-summary-open', $src);
        $this->assertStringContainsString('ota-flight-details-modal', $src);
        $this->assertStringContainsString('ota-fare-summary-modal', $src);
        $this->assertStringContainsString('data-date-price-strip', $src);
        $this->assertStringContainsString('buildFareSummaryPayload', $src);
        $this->assertStringNotContainsString('Service Charges / markup', $src);
        $this->assertStringNotContainsString('Final fare shown in PKR includes taxes, markup, and service fee.', $src);
        $this->assertStringNotContainsString('PKR total', $src);
        $this->assertStringContainsString('OtaReturnSplitCards.buildStopsLabelHtml', $src);
        $this->assertStringContainsString('buildStopsLabelHtml', $src);
    }

    public function test_results_blade_has_mobile_filter_drawer_markup(): void
    {
        $src = file_get_contents(resource_path('views/frontend/flights/results.blade.php'));
        $this->assertStringContainsString('data-filter-drawer', $src);
        $this->assertStringContainsString('data-mobile-filter-open', $src);
        $this->assertStringContainsString('data-filter-backdrop', $src);
    }

    public function test_checkout_passengers_template_shows_review_before_submit_copy(): void
    {
        $src = file_get_contents(resource_path('views/frontend/booking/passenger-details.blade.php'));
        $this->assertStringContainsString('Next: Step 4 review.', $src);
        $this->assertStringContainsString('ota-checkout-review-hint', $src);
    }

    public function test_confirmation_template_contains_next_steps_copy(): void
    {
        $src = file_get_contents(resource_path('views/frontend/booking/confirmation.blade.php'));
        $this->assertStringContainsString('Our team reviews your booking request', $src);
        $this->assertStringContainsString('data-confirmation-next-steps', $src);
    }

    public function test_login_template_has_no_demo_marketing_copy(): void
    {
        $src = file_get_contents(resource_path('views/auth/login.blade.php'));
        $this->assertFalse((bool) preg_match('/\bdemo\b/i', $src));
    }

    public function test_agent_registration_sections_are_present(): void
    {
        $src = file_get_contents(resource_path('views/frontend/agent-registration/form.blade.php'));
        foreach (['personal', 'business', 'verification', 'expected-volume', 'agreement'] as $section) {
            $this->assertStringContainsString('data-agent-section="'.$section.'"', $src);
        }
    }

    public function test_admin_api_settings_index_has_supplier_cards(): void
    {
        $src = file_get_contents(resource_path('views/dashboard/admin/api-settings/index.blade.php'));
        $this->assertStringContainsString('data-supplier-card', $src);
        $this->assertStringContainsString('Access token configured', $src);
    }

    public function test_admin_booking_show_has_pipeline_bar(): void
    {
        $src = file_get_contents(resource_path('views/dashboard/admin/bookings/show.blade.php'));
        $this->assertStringContainsString('data-booking-pipeline-bar', $src);
    }

    public function test_review_template_has_request_booking_primary_copy(): void
    {
        $src = file_get_contents(resource_path('views/frontend/booking/review.blade.php'));
        $this->assertStringContainsString('Continue to Step 5 (Confirm/payment)', $src);
    }

    public function test_core_booking_routes_remain_registered(): void
    {
        $this->assertSame('/booking/passengers', route('booking.passengers', [], false));
        $this->assertSame('/booking/review', route('booking.review', [], false));
        $this->assertSame('/booking/confirmation', route('booking.confirmation', [], false));
        $this->assertSame('/flights/results/data', route('flights.results.data', [], false));
    }
}
