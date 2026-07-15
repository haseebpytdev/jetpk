<?php

namespace App\Contracts;

interface FxRateProviderInterface
{
    /**
     * @return array{status:string, from:string, to:string, rate:float|null}
     */
    public function fetchRate(string $from, string $to): array;
}
