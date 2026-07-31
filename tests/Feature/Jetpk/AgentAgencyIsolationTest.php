<?php

namespace Tests\Feature\Jetpk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class AgentAgencyIsolationTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_agent_admin_can_access_own_agency_booking(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $booking = $scenario['recordsA']['bookings']['paid'];

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.bookings.show', ['booking' => $booking->booking_reference, 'format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('booking_reference', $booking->booking_reference);
    }

    public function test_cross_agency_booking_access_denied_without_leaking_protected_data(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $bookingB = $scenario['recordsB']['booking'];

        $response = $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.bookings.show', ['booking' => $bookingB->booking_reference, 'format' => 'json']));

        $response->assertForbidden();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('BKG-B-ONLY', $body);
        $this->assertStringNotContainsString('Beta Traveler', $body);
    }

    public function test_agency_booking_list_excludes_other_agency_records(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $bookingA = $scenario['recordsA']['bookings']['paid'];

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.bookings.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonFragment(['booking_reference' => $bookingA->booking_reference])
            ->assertJsonMissing(['booking_reference' => 'BKG-B-ONLY']);
    }

    public function test_wallet_ledger_and_deposits_remain_scoped_to_authenticated_agency(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $txB = $scenario['recordsB']['transaction'];

        $this->actingAs($scenario['adminA'])->get(route('agent.wallet.show'))
            ->assertOk()
            ->assertSee('25,000.00', false)
            ->assertDontSee('LEDGER-B-ONLY', false)
            ->assertDontSee('DEP-B-ONLY', false);

        $this->actingAs($scenario['staff']['A4'])->get(route('agent.ledger.index'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-ledger-row-'.$txB->id.'"', false)
            ->assertDontSee('LEDGER-B-ONLY', false);

        $this->actingAs($scenario['staff']['A3'])->get(route('agent.deposits.index'))
            ->assertOk()
            ->assertDontSee('DEP-B-ONLY', false);
    }

    public function test_cross_agency_denied_json_does_not_expose_wallet_or_deposit_values(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $bookingB = $scenario['recordsB']['booking'];

        $response = $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.bookings.show', ['booking' => $bookingB->booking_reference, 'format' => 'json']));

        $response->assertForbidden();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString((string) $scenario['walletB']->balance, $body);
        $this->assertStringNotContainsString('DEP-B-ONLY', $body);
        $this->assertStringNotContainsString('LEDGER-B-ONLY', $body);
    }
}
