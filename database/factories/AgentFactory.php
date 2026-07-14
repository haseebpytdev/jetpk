<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'user_id' => User::factory(),
            'code' => fake()->bothify('AGT-####'),
            'commission_percent' => fake()->randomFloat(2, 1, 15),
            'is_active' => true,
            'meta' => null,
        ];
    }
}
