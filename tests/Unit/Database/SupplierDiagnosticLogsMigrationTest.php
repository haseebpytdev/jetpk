<?php

namespace Tests\Unit\Database;

use Tests\TestCase;

class SupplierDiagnosticLogsMigrationTest extends TestCase
{
    public function test_migration_declares_explicit_live_index_name(): void
    {
        $path = database_path('migrations/2026_05_06_141500_create_supplier_diagnostic_logs_table.php');
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringContainsString('sdl_conn_action_status_idx', $source);
        $this->assertStringContainsString("['supplier_connection_id', 'action', 'status']", $source);
    }
}
