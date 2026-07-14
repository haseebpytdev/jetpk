<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'current_agency_id' => null,
            'account_type' => null,
            'status' => UserAccountStatus::Active,
            'invited_at' => null,
            'last_login_at' => null,
            'meta' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function agencyAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::AgencyAdmin,
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Staff,
        ]);
    }

    public function agent(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Agent,
        ]);
    }

    public function agentStaff(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::AgentStaff,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Customer,
        ]);
    }
}
