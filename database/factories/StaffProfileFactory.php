<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffProfile>
 */
class StaffProfileFactory extends Factory
{
    protected $model = StaffProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'user_id' => User::factory(),
            'job_title' => fake()->jobTitle(),
            'department' => fake()->randomElement(['Operations', 'Finance', 'Support']),
            'is_active' => true,
        ];
    }
}
