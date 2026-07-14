<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\User;
use App\Services\Suppliers\Iati\IatiTicketingService;
use Illuminate\Console\Command;

class IatiTestTicketingCommand extends Command
{
    protected $signature = 'iati:test-ticketing {booking : OTA booking ID}';

    protected $description = 'Issue IATI tickets (option→book) for a booking';

    public function handle(IatiTicketingService $ticketingService): int
    {
        $booking = Booking::query()->with('latestSupplierBooking.supplierConnection')->find((int) $this->argument('booking'));
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $supplierBooking = $booking->latestSupplierBooking;
        if ($supplierBooking === null || $supplierBooking->provider !== SupplierProvider::Iati->value) {
            $this->error('No IATI supplier booking record.');

            return self::FAILURE;
        }

        $actor = User::query()->first();
        if ($actor === null) {
            $this->error('No user available.');

            return self::FAILURE;
        }

        $result = $ticketingService->issueTickets($booking, $supplierBooking, $actor);
        $this->line('success='.($result->success ? 'true' : 'false'));
        $this->line('status='.$result->status);
        $this->line('tickets='.json_encode($result->tickets));

        return $result->success ? self::SUCCESS : self::FAILURE;
    }
}
