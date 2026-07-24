<?php

namespace App\Services\Suppliers\OneApi\Reservation;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;

class OneApiHoldPaymentService
{
    public function __construct(
        private readonly OneApiConfigResolver $configResolver,
        private readonly OneApiRetrieveService $retrieveService,
        private readonly OneApiSoapTransportContract $soapTransport,
    ) {}

    public function canPayHeldReservation(Booking $booking, SupplierConnection $connection): bool
    {
        $config = $this->configResolver->resolve($connection);

        return (bool) ($config['hold_payment_enabled'] ?? false)
            && (bool) ($config['live_payment_modification_enabled'] ?? false)
            && trim((string) ($booking->pnr ?? '')) !== '';
    }

    /**
     * Single modification attempt; ambiguous outcomes require reconciliation (no retry).
     *
     * @param  array<string, mixed>  $diagnosticContext
     */
    public function payHeldReservation(
        Booking $booking,
        SupplierConnection $connection,
        array $diagnosticContext = [],
    ): array {
        if (! $this->canPayHeldReservation($booking, $connection)) {
            return ['success' => false, 'error_code' => 'payment_rejected'];
        }

        $pnr = (string) $booking->pnr;
        $sessionKey = 'hold-pay:'.$booking->id;
        $this->retrieveService->getReservationByPnr($connection, $pnr, $sessionKey, $diagnosticContext);

        $xml = (string) ($diagnosticContext['modify_request_xml'] ?? '<soapenv:Envelope/>');

        return $this->soapTransport->call($connection, 'modify', $xml, $sessionKey, $diagnosticContext);
    }
}
