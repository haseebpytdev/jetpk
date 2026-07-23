<?php

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\FlightSupplierInterface;
use App\Enums\SupplierProvider;
use App\Services\Suppliers\Adapters\AirBlueFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\AirlineDirectFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\IatiFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\OneApiFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\PiaNdcFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\SabreFlightSupplierAdapter;
use InvalidArgumentException;

class SupplierAdapterResolver
{
    public function __construct(
        protected SabreFlightSupplierAdapter $sabreAdapter,
        protected PiaNdcFlightSupplierAdapter $piaNdcAdapter,
        protected AirBlueFlightSupplierAdapter $airBlueAdapter,
        protected AirlineDirectFlightSupplierAdapter $airlineDirectAdapter,
        protected DuffelFlightSupplierAdapter $duffelAdapter,
        protected IatiFlightSupplierAdapter $iatiAdapter,
        protected OneApiFlightSupplierAdapter $oneApiAdapter,
    ) {}

    public function resolve(SupplierProvider $provider): FlightSupplierInterface
    {
        return match ($provider) {
            SupplierProvider::Sabre => $this->sabreAdapter,
            SupplierProvider::PiaNdc => $this->piaNdcAdapter,
            SupplierProvider::Airblue => $this->airBlueAdapter,
            SupplierProvider::AirlineDirect => $this->airlineDirectAdapter,
            SupplierProvider::Duffel => $this->duffelAdapter,
            SupplierProvider::Iati => $this->iatiAdapter,
            SupplierProvider::OneApi => $this->oneApiAdapter,
            default => throw new InvalidArgumentException('Unsupported supplier provider: '.$provider->value),
        };
    }
}
