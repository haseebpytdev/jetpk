<?php

namespace App\Data;

class SupplierHoldPaymentResultData
{
    public function __construct(
        public bool $success,
        public string $code,
        public string $message,
    ) {}
}
