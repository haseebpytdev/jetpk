<?php

namespace App\Services\Suppliers\PiaNdc;

use Illuminate\Support\Str;

class PiaNdcCorrelationContext
{
    public function newCorrelationId(): string
    {
        return (string) Str::uuid();
    }
}
