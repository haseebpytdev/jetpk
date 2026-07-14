<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPassenger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPassenger>
 */
class BookingPassengerFactory extends Factory
{
    protected $model = BookingPassenger::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'passenger_index' => 0,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'title' => fake()->randomElement(['Mr', 'Ms', 'Mrs']),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->date(),
            'nationality' => 'PK',
            'passport_number' => null,
            'meta' => null,
        ];
    }
}
