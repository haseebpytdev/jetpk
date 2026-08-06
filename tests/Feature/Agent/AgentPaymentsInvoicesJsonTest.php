<?php

namespace Tests\Feature\Agent;

use App\Enums\AccountType;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class AgentPaymentsInvoicesJsonTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_agent_admin_receives_payments_json_for_own_agency(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.payments.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['payments', 'pagination', 'filter']);
    }

    public function test_agent_staff_with_wallet_view_can_fetch_payments_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A5'])
            ->getJson(route('agent.payments.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_agent_staff_without_wallet_view_is_denied_payments_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A0'])
            ->getJson(route('agent.payments.index', ['format' => 'json']))
            ->assertForbidden();
    }

    public function test_agent_admin_receives_invoices_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.invoices.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['invoices', 'pagination']);
    }

    public function test_payments_json_excludes_other_agency_booking_reference(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $bookingB = $scenario['recordsB']['booking'];

        $response = $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.payments.index', ['format' => 'json']))
            ->assertOk();

        $this->assertStringNotContainsString(
            (string) $bookingB->booking_reference,
            json_encode($response->json('payments'), JSON_THROW_ON_ERROR),
        );
    }

    public function test_customer_is_denied_agent_payments_json(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $this->actingAs($customer)
            ->getJson(route('agent.payments.index', ['format' => 'json']))
            ->assertForbidden();
    }

    public function test_guest_is_denied_agent_payments_json(): void
    {
        $this->getJson(route('agent.payments.index', ['format' => 'json']))
            ->assertUnauthorized();
    }

    public function test_payments_json_does_not_expose_provider_secrets(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $content = $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.payments.index', ['format' => 'json']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('super-secret', $content);
        $this->assertStringNotContainsString('gateway_secret', $content);
    }
}
