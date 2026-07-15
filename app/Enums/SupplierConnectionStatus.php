<?php

namespace App\Enums;

enum SupplierConnectionStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Testing = 'testing';
    case Error = 'error';
}
