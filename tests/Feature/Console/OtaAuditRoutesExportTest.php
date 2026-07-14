<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OtaAuditRoutesExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_routes_export_writes_non_empty_markdown_with_positive_bucket_counts(): void
    {
        $path = storage_path('framework/testing/route-inventory-test.md');

        if (File::exists($path)) {
            File::delete($path);
        }

        $this->artisan('ota:audit-routes', ['--export' => $path])
            ->assertSuccessful();

        $this->assertFileExists($path);
        $content = File::get($path);
        $this->assertStringContainsString('# OTA Route Inventory', $content);
        $this->assertStringContainsString('| Total routes |', $content);
        $this->assertMatchesRegularExpression('/\| Total routes \| [1-9]\d* \|/', $content);

        File::delete($path);
    }
}
