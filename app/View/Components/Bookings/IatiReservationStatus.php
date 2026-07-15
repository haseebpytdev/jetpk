<?php

namespace App\View\Components\Bookings;

use App\Models\Booking;
use App\Support\Bookings\IatiReservationLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class IatiReservationStatus extends Component
{
    /** @var array<string, mixed> */
    public array $presentation;

    public function __construct(
        public Booking $booking,
        public string $variant = 'customer',
    ) {
        $this->presentation = app(IatiReservationLifecycleService::class)->presentation($booking->fresh());
    }

    public function render(): View
    {
        return view('components.bookings.iati-reservation-status');
    }
}
