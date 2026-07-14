<?php

namespace App\Services\Suppliers\Iati;

use Illuminate\Support\Str;

class IatiCorrelationContext
{
    public function newCorrelationId(): string
    {
        return (string) Str::uuid();
    }
}
