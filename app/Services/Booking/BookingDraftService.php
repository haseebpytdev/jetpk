<?php

namespace App\Services\Booking;

use Illuminate\Support\Facades\Session;

class BookingDraftService
{
    protected const SESSION_KEY = 'ota_booking_draft';

    /**
     * @param  array<string, mixed>  $data
     */
    public function merge(array $data): void
    {
        Session::put(self::SESSION_KEY, array_merge($this->current(), $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function savePassengers(array $data): void
    {
        $current = $this->current();
        Session::put(self::SESSION_KEY, array_merge($current, $data, [
            'submitted_at' => now()->toIso8601String(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
