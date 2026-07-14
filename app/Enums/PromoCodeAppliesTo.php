<?php

namespace App\Enums;

enum PromoCodeAppliesTo: string
{
    case Flights = 'flights';
    case GroupTicketing = 'group_ticketing';
    case All = 'all';
}
