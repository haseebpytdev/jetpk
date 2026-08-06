<?php

namespace Tests\Feature\Agent;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\SavedTraveler;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class AgentTravelersJsonTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_agent_admin_can_list_travelers_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.travelers.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['travelers', 'pagination', 'countries', 'create_url'])
            ->assertJsonFragment(['first_name' => 'Complete']);
    }

    public function test_agent_staff_with_permission_can_list_travelers_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A8'])
            ->getJson(route('agent.travelers.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_agent_staff_without_permission_is_denied_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A0'])
            ->getJson(route('agent.travelers.index', ['format' => 'json']))
            ->assertForbidden();
    }

    public function test_agent_can_create_traveler_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->postJson(route('agent.travelers.store', ['format' => 'json']), $this->travelerPayload())
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('redirect_url', '/agent/travelers');

        $this->assertDatabaseHas('saved_travelers', [
            'agency_id' => $scenario['agencyA']->id,
            'first_name' => 'Sara',
        ]);
    }

    public function test_agent_can_update_traveler_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $traveler = $scenario['recordsA']['travelers']['complete'];

        $this->actingAs($scenario['adminA'])
            ->patchJson(route('agent.travelers.update', ['traveler' => $traveler, 'format' => 'json']), array_merge(
                $this->travelerPayload(),
                ['first_name' => 'Updated'],
            ))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('traveler.first_name', 'Updated');
    }

    public function test_agent_can_delete_traveler_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $traveler = $scenario['recordsA']['travelers']['incomplete'];

        $this->actingAs($scenario['adminA'])
            ->deleteJson(route('agent.travelers.destroy', ['traveler' => $traveler, 'format' => 'json']))
            ->assertOk()
            ->assertJsonPath('redirect_url', '/agent/travelers');

        $this->assertDatabaseMissing('saved_travelers', ['id' => $traveler->id]);
    }

    public function test_validation_errors_return_422_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->postJson(route('agent.travelers.store', ['format' => 'json']), ['first_name' => 'Only'])
            ->assertStatus(422);
    }

    public function test_cross_agency_traveler_edit_is_denied(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.travelers.edit', [
                'traveler' => $scenario['recordsB']['traveler'],
                'format' => 'json',
            ]))
            ->assertForbidden();
    }

    public function test_customer_cannot_access_agent_travelers_json(): void
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
            ->getJson(route('agent.travelers.index', ['format' => 'json']))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_agent_travelers_json(): void
    {
        $this->getJson(route('agent.travelers.index', ['format' => 'json']))
            ->assertUnauthorized();
    }

    public function test_list_json_masks_document_number(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $traveler = $scenario['recordsA']['travelers']['complete'];

        $response = $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.travelers.index', ['format' => 'json']))
            ->assertOk();

        $payload = collect($response->json('travelers'))->firstWhere('id', $traveler->id);
        $this->assertNotNull($payload);
        $this->assertArrayHasKey('document_number_masked', $payload);
        $this->assertArrayNotHasKey('document_number', $payload);
    }

    public function test_client_supplied_agency_id_is_ignored_on_create(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->postJson(route('agent.travelers.store', ['format' => 'json']), array_merge(
                $this->travelerPayload(),
                ['agency_id' => $scenario['agencyB']->id, 'user_id' => $scenario['adminB']->id],
            ))
            ->assertCreated();

        $this->assertDatabaseHas('saved_travelers', [
            'agency_id' => $scenario['agencyA']->id,
            'user_id' => $scenario['adminA']->id,
        ]);
        $this->assertDatabaseMissing('saved_travelers', [
            'agency_id' => $scenario['agencyB']->id,
            'first_name' => 'Sara',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function travelerPayload(): array
    {
        return [
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
            'title' => 'Ms',
            'gender' => 'female',
            'date_of_birth' => '1990-05-15',
            'nationality' => 'PK',
            'document_type' => 'passport',
            'document_number' => 'PK1234567890',
            'document_expiry' => now()->addYears(2)->format('Y-m-d'),
            'issuing_country' => 'PK',
            'phone' => '03001234567',
            'email' => 'sara@example.test',
            'is_default' => false,
        ];
    }
}
