<?php

namespace Tests\Unit\Ai;

use Tests\TestCase;

/**
 * Contract guard for harness timing helper exports (JP-AI-PERFORMANCE-MEASUREMENT-CORRECTION-16).
 */
class PerformanceMeasurementHelperTest extends TestCase
{
    public function test_canary_matrix_helpers_export_timing_functions(): void
    {
        $path = base_path('frontend/scripts/canary-matrix-helpers.mjs');
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertIsString($source);
        $this->assertStringContainsString('export function normalizeAssistantVisibleText', $source);
        $this->assertStringContainsString('export async function readAssistantMessageBodies', $source);
        $this->assertStringContainsString('dom_render_timeout', $source);
        $this->assertStringContainsString('api_response_ms', $source);
        $this->assertStringContainsString('measurement_valid', $source);
    }

    public function test_performance_measurement_runner_exists(): void
    {
        $this->assertFileExists(base_path('frontend/scripts/run-performance-measurement-16.mjs'));
    }
}
