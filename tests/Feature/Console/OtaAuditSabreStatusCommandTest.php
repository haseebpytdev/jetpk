<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OtaAuditSabreStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_command_is_read_only_and_redacts_secrets(): void
    {
        $export = 'storage/app/test-sabre-status-report.md';
        File::ensureDirectoryExists(base_path('storage/app'));

        $this->artisan('ota:audit-sabre-status', ['--export' => $export])
            ->expectsOutputToContain('Classification: READ-ONLY')
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->assertSuccessful();

        $this->assertFileExists(base_path($export));
        $contents = (string) file_get_contents(base_path($export));

        $this->assertStringContainsString('READ-ONLY', $contents);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $contents);
        $this->assertStringNotContainsString('client_secret', strtolower($contents));
        $this->assertStringNotContainsString('Bearer eyJ', $contents);

        @unlink(base_path($export));
    }
}
