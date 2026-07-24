<?php

namespace App\Contracts\Suppliers\OneApi;

use App\Models\SupplierConnection;

interface OneApiSoapTransportContract
{
    /**
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function call(
        SupplierConnection $connection,
        string $operation,
        string $requestXml,
        string $workflowSessionKey,
        array $diagnosticContext = [],
    ): array;
}
