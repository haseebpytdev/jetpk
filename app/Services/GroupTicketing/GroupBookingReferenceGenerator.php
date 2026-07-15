<?php

namespace App\Services\GroupTicketing;

use App\Support\References\CompactReferenceGenerator;

class GroupBookingReferenceGenerator
{
    public function __construct(
        protected CompactReferenceGenerator $referenceGenerator,
    ) {}

    public function generate(): string
    {
        return $this->referenceGenerator->generateUnique('group_bookings', 'reference', 8);
    }
}
