<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\SavedTraveler;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedTravelerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_customer_can_create_saved_traveler(): void
    {
        $customer = $this->customerUser();

        $this->actingAs($customer)->post(route('customer.travelers.store'), $this->travelerPayload())
            ->assertRedirect(route('customer.travelers.index'))
            ->assertSessionHas('status', 'traveler-saved');

        $this->assertDatabaseHas('saved_travelers', [
            'user_id' => $customer->id,
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
        ]);
    }

    public function test_customer_can_edit_and_delete_own_traveler(): void
    {
        $customer = $this->customerUser();
        $traveler = $this->travelerForUser($customer);

        $this->actingAs($customer)->patch(route('customer.travelers.update', $traveler), array_merge(
            $this->travelerPayload(),
            ['first_name' => 'Updated', 'document_number' => 'PK9988776655'],
        ))->assertRedirect(route('customer.travelers.index'));

        $this->assertSame('Updated', $traveler->fresh()->first_name);

        $this->actingAs($customer)->delete(route('customer.travelers.destroy', $traveler))
            ->assertRedirect(route('customer.travelers.index'));

        $this->assertDatabaseMissing('saved_travelers', ['id' => $traveler->id]);
    }

    public function test_customer_cannot_access_another_customers_traveler(): void
    {
        $customer = $this->customerUser();
        $other = $this->customerUser('other-customer@example.test');
        $traveler = $this->travelerForUser($other);

        $this->actingAs($customer)->get(route('customer.travelers.edit', $traveler))->assertForbidden();
        $this->actingAs($customer)->patch(route('customer.travelers.update', $traveler), $this->travelerPayload())->assertForbidden();
        $this->actingAs($customer)->delete(route('customer.travelers.destroy', $traveler))->assertForbidden();
    }

    public function test_agent_can_create_saved_traveler(): void
    {
        [$agentUser] = $this->seededAgent();

        $this->actingAs($agentUser)->post(route('agent.travelers.store'), $this->travelerPayload())
            ->assertRedirect(route('agent.travelers.index'));

        $this->assertDatabaseHas('saved_travelers', [
            'user_id' => $agentUser->id,
            'first_name' => 'Sara',
        ]);
    }

    public function test_agent_cannot_access_another_agents_traveler(): void
    {
        [$agentUser] = $this->seededAgent();
        $otherAgent = $this->otherAgentUser();
        $traveler = $this->travelerForUser($otherAgent, $otherAgent->current_agency_id);

        $this->actingAs($agentUser)->get(route('agent.travelers.edit', $traveler))->assertForbidden();
    }

    public function test_traveler_list_masks_document_number(): void
    {
        $customer = $this->customerUser();
        $traveler = $this->travelerForUser($customer, documentNumber: 'PK1234567890');

        $this->actingAs($customer)->get(route('customer.travelers.index'))
            ->assertOk()
            ->assertSee('PK****7890', false)
            ->assertDontSee('PK1234567890', false);
    }

    public function test_traveler_completeness_status_works(): void
    {
        $customer = $this->customerUser();
        $traveler = SavedTraveler::query()->create([
            'user_id' => $customer->id,
            'agency_id' => $customer->current_agency_id,
            'first_name' => 'Min',
            'last_name' => 'Profile',
        ]);

        $this->actingAs($customer)->get(route('customer.travelers.index'))
            ->assertOk()
            ->assertSee('data-testid="traveler-completeness-warning-'.$traveler->id.'"', false)
            ->assertSee('incomplete', false);

        $traveler->update(array_merge($this->travelerPayload(), [
            'first_name' => 'Min',
            'last_name' => 'Profile',
        ]));

        $this->actingAs($customer)->get(route('customer.travelers.index'))
            ->assertOk()
            ->assertSee('complete', false)
            ->assertDontSee('data-testid="traveler-completeness-warning-'.$traveler->id.'"', false);
    }

    public function test_customer_account_nav_renders_travelers_link(): void
    {
        $customer = $this->customerUser();

        $this->actingAs($customer)->get(route('customer.travelers.index'))
            ->assertOk()
            ->assertSee('data-testid="customer-account-subnav"', false)
            ->assertSee(route('customer.travelers.index'), false)
            ->assertSee('data-testid="default-traveler-card"', false);
    }

    public function test_default_traveler_profile_card_shows_without_creating_row(): void
    {
        $customer = $this->customerUser();

        $this->actingAs($customer)->get(route('customer.travelers.index'))
            ->assertOk()
            ->assertSee('data-testid="default-traveler-card"', false)
            ->assertSee('data-testid="default-traveler-complete-profile"', false);

        $this->assertDatabaseCount('saved_travelers', 0);
    }

    public function test_default_traveler_incomplete_badge_from_profile(): void
    {
        $customer = $this->customerUser();

        $this->actingAs($customer)->get(route('customer.travelers.index'))
            ->assertOk()
            ->assertSee('data-testid="default-traveler-incomplete"', false);
    }

    public function test_saved_default_traveler_not_duplicated_in_list(): void
    {
        $customer = $this->customerUser();
        $traveler = $this->travelerForUser($customer);
        $traveler->update(['is_default' => true]);

        $response = $this->actingAs($customer)->get(route('customer.travelers.index'));
        $response->assertOk();
        $response->assertSee('data-testid="default-traveler-card"', false);
        $response->assertDontSee('data-testid="saved-traveler-row-'.$traveler->id.'"', false);
    }

    public function test_agent_sidebar_renders_travelers_link(): void
    {
        [$agentUser] = $this->seededAgent();

        $this->actingAs($agentUser)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="agent-sidebar-travelers"', false)
            ->assertSee(route('agent.travelers.index'), false);
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
            'is_default' => true,
        ];
    }

    protected function customerUser(string $email = 'saved-traveler-customer@example.test'): User
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $customer = User::factory()->create([
            'email' => $email,
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        return $customer;
    }

    protected function travelerForUser(User $user, ?int $agencyId = null, ?string $documentNumber = null): SavedTraveler
    {
        return SavedTraveler::query()->create(array_merge(
            $this->travelerPayload(),
            [
                'user_id' => $user->id,
                'agency_id' => $agencyId ?? $user->current_agency_id,
                'document_number' => $documentNumber ?? 'PK1234567890',
            ],
        ));
    }

    /**
     * @return array{0: User, 1: Agent}
     */
    protected function seededAgent(): array
    {
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        return [$agentUser, $agent];
    }

    protected function otherAgentUser(): User
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $otherUser = User::factory()->agent()->create(['current_agency_id' => $agency->id]);
        $agency->users()->attach($otherUser->id, ['role' => 'agent']);
        Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $otherUser->id,
        ]);

        return $otherUser;
    }
}
