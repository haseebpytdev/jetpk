<?php

namespace Tests\Feature;

use App\Support\PublicBooking;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Sabre\SabrePublicCreatePhase17ETestCase;

/**
 * Phase 18G: JetPakistan checkout continuity for guest/customer/agent (fake HTTP only).
 */
class SabreGdsCheckoutRoleParityPhase18GTest extends SabrePublicCreatePhase17ETestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Http::fake();
    }

    public function test_guest_passengers_page_uses_jetpakistan_theme_without_master_fallback(): void
    {
        $html = $this->followingRedirects()
            ->get(route('booking.passengers'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('jetpakistan', strtolower($html));
        $this->assertStringNotContainsStringIgnoringCase('parwaaz', $html);
        $this->assertStringNotContainsStringIgnoringCase('master-client', $html);
        $this->assertStringNotContainsStringIgnoringCase('yourdomain', $html);
    }

    public function test_customer_review_page_renders_jetpakistan_checkout_shell(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();
        $customer = $this->customerUser();

        $html = $this->actingAs($customer)
            ->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->get(route('booking.review'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('booking', strtolower($html));
        $this->assertStringNotContainsStringIgnoringCase('parwaaz', $html);
        $this->assertStringNotContainsStringIgnoringCase('yourdomain', $html);
    }

    public function test_agent_review_page_renders_without_master_fallback(): void
    {
        $booking = $this->makeFreshSabreDraftBooking();
        $agentUser = $this->agentUser();

        $html = $this->actingAs($agentUser)
            ->withSession(array_merge(
                [PublicBooking::SESSION_BOOKING_ID => $booking->id],
                $this->agentSessionContext(),
            ))
            ->get(route('booking.review'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsStringIgnoringCase('parwaaz', $html);
        $this->assertStringNotContainsStringIgnoringCase('yourdomain', $html);
        $this->assertStringNotContainsStringIgnoringCase('master-client', $html);
    }

    public function test_draft_review_page_does_not_claim_ticketed_when_ticketing_disabled(): void
    {
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking = $this->makeFreshSabreDraftBooking();

        $html = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->get(route('booking.review'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsStringIgnoringCase('ticket issued', $html);
        $this->assertStringNotContainsStringIgnoringCase('e-ticket number', $html);
    }
}
