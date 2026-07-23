<?php

namespace App\Enums;

enum SupplierProvider: string
{
    case Sabre = 'sabre';
    case PiaNdc = 'pia_ndc';
    case Airblue = 'airblue';
    case AirlineDirect = 'airline_direct';
    case Duffel = 'duffel';
    case Iati = 'iati';
    case OneApi = 'one_api';
    case Amadeus = 'amadeus';
    case Travelport = 'travelport';
}
