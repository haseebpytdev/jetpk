<?php

namespace Database\Factories;

use App\Enums\AgentWalletStatus;
use App\Models\Agent;
use App\Models\AgentWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentWallet>
 */
class AgentWalletFactory extends Factory
{
    protected $model = AgentWallet::class;

    public function definition(): array
    {
        $agent = Agent::factory()->create();

        return [
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'balance' => 0,
            'credit_limit' => null,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            // Default NULL so multi-wallet historical fixtures remain valid under the unique index.
            'canonical_for_agency' => null,
        ];
    }

    public function canonical(): static
    {
        return $this->state(fn (): array => [
            'canonical_for_agency' => AgentWallet::CANONICAL_MARKER,
        ]);
    }
}
