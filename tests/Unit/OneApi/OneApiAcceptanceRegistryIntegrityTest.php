<?php

namespace Tests\Unit\OneApi;

use Tests\Support\OneApi\OneApiAcceptanceRequiredIdRegistry;
use Tests\Support\OneApi\OneApiAcceptanceRequirementMap;
use Tests\TestCase;

class OneApiAcceptanceRegistryIntegrityTest extends TestCase
{
    public function test_map_contains_every_registry_id_with_unchanged_mandatory_flag(): void
    {
        $registry = OneApiAcceptanceRequiredIdRegistry::entries();
        $byId = [];
        foreach (OneApiAcceptanceRequirementMap::requirements() as $row) {
            $byId[$row['id']] = $row;
        }

        foreach ($registry as $entry) {
            $id = $entry['id'];
            $this->assertArrayHasKey($id, $byId, "Requirement map missing registry ID {$id}");
            $this->assertTrue($byId[$id]['mandatory'], "Requirement {$id} mandatory flag downgraded.");
            $this->assertSame($entry['source_phase'], $byId[$id]['source_phase'], "Requirement {$id} source_phase mismatch.");
        }

        foreach (array_keys($byId) as $id) {
            $this->assertContains($id, OneApiAcceptanceRequiredIdRegistry::ids(), "Unexpected requirement ID in map: {$id}");
        }
    }
}
