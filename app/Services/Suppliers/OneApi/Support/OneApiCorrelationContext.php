<?php

namespace App\Services\Suppliers\OneApi\Support;

use Illuminate\Support\Str;

class OneApiCorrelationContext
{
    public function newCorrelationId(): string
    {
        return (string) Str::uuid();
    }
}
