<?php

namespace Tests\Unit\OneApi;

use Tests\Support\OneApi\OneApiAcceptanceRequirementMap;
use Tests\TestCase;

class OneApiAcceptanceRequirementGateTest extends TestCase
{
    public function test_no_mandatory_acceptance_requirements_remain_missing(): void
    {
        $missing = OneApiAcceptanceRequirementMap::mandatoryMissing();
        $this->assertSame([], $missing, 'Mandatory acceptance requirements still missing: '.implode('; ', $missing));
    }
}
