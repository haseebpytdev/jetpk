<?php

namespace Database\Factories;

use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupRuleType;
use App\Enums\MarkupValueType;
use App\Models\Agency;
use App\Models\MarkupRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarkupRule>
 */
class MarkupRuleFactory extends Factory
{
    protected $model = MarkupRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'name' => fake()->words(3, true).' markup',
            'rule_type' => MarkupRuleType::Global,
            'value' => fake()->randomFloat(4, 1, 12),
            'value_type' => MarkupValueType::Percentage,
            'applies_to' => null,
            'priority' => 100,
            'status' => MarkupRuleStatus::Active,
            'starts_at' => null,
            'ends_at' => null,
            'meta' => null,
            'config' => null,
            'is_active' => true,
        ];
    }
}
