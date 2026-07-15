<?php

namespace App\Enums;

enum MarkupRuleType: string
{
    case Global = 'global';
    case Route = 'route';
    case Airline = 'airline';
    case Supplier = 'supplier';
    case Agent = 'agent';
    case Cabin = 'cabin';
    case FareFamily = 'fare_family';
}
