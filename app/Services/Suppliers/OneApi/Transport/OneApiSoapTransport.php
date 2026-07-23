<?php

namespace App\Services\Suppliers\OneApi\Transport;

use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;

/**
 * @deprecated Prefer type-hinting {@see OneApiSoapTransportContract}.
 */
class OneApiSoapTransport extends LiveOneApiSoapTransport implements OneApiSoapTransportContract {}
