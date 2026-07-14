<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'customer_id' => null,
            'agent_id' => null,
            'supplier' => null,
            'route' => null,
            'airline' => null,
            'travel_date' => null,
            'booking_reference' => null,
            'status' => BookingStatus::Draft,
            'payment_status' => 'unpaid',
            'payment_due_at' => null,
            'amount_paid' => 0,
            'balance_due' => null,
            'source_channel' => null,
            'currency' => 'PKR',
            'pnr' => null,
            'notes' => null,
            'meta' => null,
            'submitted_at' => null,
            'confirmed_at' => null,
        ];
    }
}
