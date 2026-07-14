<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\SavedTraveler;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedTraveler>
 */
class SavedTravelerFactory extends Factory
{
    protected $model = SavedTraveler::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'agency_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'title' => 'Mr',
            'gender' => 'male',
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'nationality' => 'PK',
            'document_type' => 'passport',
            'document_number' => 'PK'.fake()->numerify('########'),
            'document_expiry' => now()->addYears(3),
            'issuing_country' => 'PK',
            'phone' => fake()->numerify('03#########'),
            'email' => fake()->safeEmail(),
            'is_default' => false,
            'meta' => null,
        ];
    }

    public function forAgency(Agency $agency): static
    {
        return $this->state(fn () => ['agency_id' => $agency->id]);
    }
}
