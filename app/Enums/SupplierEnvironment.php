<?php

namespace App\Enums;

enum SupplierEnvironment: string
{
    case Demo = 'demo';
    case Sandbox = 'sandbox';
    case Live = 'live';
}
