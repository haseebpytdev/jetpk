<?php

namespace Database\Factories;

use App\Enums\PromoCodeAppliesTo;
use App\Enums\PromoCodeStatus;
use App\Enums\PromoCodeType;
use App\Models\Agency;
use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'code' => strtoupper(fake()->unique()->bothify('PROMO-####')),
            'name' => fake()->words(2, true),
            'type' => PromoCodeType::Percent,
            'value' => fake()->randomFloat(2, 5, 20),
            'currency' => null,
            'min_amount' => null,
            'max_discount' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'usage_limit' => 100,
            'used_count' => 0,
            'applies_to' => PromoCodeAppliesTo::Flights,
            'status' => PromoCodeStatus::Active,
            'internal_testing_only' => false,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => PromoCodeStatus::Inactive,
        ]);
    }
}
