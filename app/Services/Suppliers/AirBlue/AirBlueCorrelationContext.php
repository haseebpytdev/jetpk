<?php

namespace App\Services\Suppliers\AirBlue;

use Illuminate\Support\Str;

class AirBlueCorrelationContext
{
    public function newCorrelationId(): string
    {
        return (string) Str::uuid();
    }
}
