<?php

namespace Tests\Unit\OneApi;

use Tests\Support\OneApi\OneApiAcceptanceRequiredIdRegistry;
use Tests\Support\OneApi\OneApiAcceptanceRequirementMap;
use Tests\TestCase;

class OneApiPhase9OpenRequirementsIntegrityTest extends TestCase
{
    public function test_phase9_open_ids_remain_in_registry_until_covered(): void
    {
        $byId = [];
        foreach (OneApiAcceptanceRequirementMap::requirements() as $row) {
            $byId[$row['id']] = $row;
        }

        foreach (OneApiAcceptanceRequiredIdRegistry::phase9OpenUntilCoveredIds() as $id) {
            $this->assertContains($id, OneApiAcceptanceRequiredIdRegistry::ids());
            $this->assertArrayHasKey($id, $byId);
            $this->assertTrue($byId[$id]['mandatory'], "{$id} mandatory flag must remain true.");
            $this->assertSame(
                'covered',
                $byId[$id]['status'],
                "{$id} must be covered with exact test evidence before removing from phase9OpenUntilCoveredIds()."
            );
        }
    }
}
