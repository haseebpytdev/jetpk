<?php

namespace App\Services\Suppliers\TicketingAdapters;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostPnrService;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostTicketingService;

/**
 * Thin ticketing adapter for AEROST UAT issuance.
 */
class EjmiralAerostSupplierTicketingAdapter
{
    public function __construct(
        private readonly EjmiralAerostTicketingService $ticketingService,
        private readonly EjmiralAerostPnrService $pnrService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function issue(Booking $booking, SupplierConnection $connection, string $ticketRef, float $cashAmount): array
    {
        if ($connection->provider !== SupplierProvider::EjmiralAerost) {
            return [
                'success' => false,
                'error_code' => 'supplier_provider_mismatch',
            ];
        }

        $result = $this->ticketingService->ticket($connection, $ticketRef, $cashAmount);
        $pnr = (string) ($booking->pnr ?: $booking->supplier_reference);
        if ($pnr !== '') {
            $result['post_ticket_pnr'] = $this->pnrService->pnrInfo($connection, $pnr);
        }

        return $result;
    }
}
