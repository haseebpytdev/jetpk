<?php

namespace App\Services\Suppliers\OneApi\Reservation;

use App\Models\SupplierConnection;
use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;

class OneApiRetrieveService
{
    public function __construct(
        private readonly OneApiSoapTransportContract $soapTransport,
        private readonly OneApiReadRequestBuilder $readRequestBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function getReservationByPnr(
        SupplierConnection $connection,
        string $pnr,
        string $workflowSessionKey,
        array $diagnosticContext = [],
    ): array {
        $xml = (string) ($diagnosticContext['read_request_xml'] ?? '');
        if ($xml === '') {
            $xml = $this->readRequestBuilder->build($connection, $pnr);
        }

        return $this->soapTransport->call($connection, 'read', $xml, $workflowSessionKey, $diagnosticContext);
    }
}
