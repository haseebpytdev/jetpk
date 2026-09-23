<?php

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiEmbedTenantRegistryMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_declares_ai_conversation_id_anchor_not_conversation_id(): void
    {
        $path = database_path('migrations/2026_09_23_140000_create_ai_embed_tenant_registry_tables.php');
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringContainsString("->after('ai_conversation_id')", $source);
        $this->assertStringNotContainsString("->after('conversation_id')", $source);
    }

    public function test_migration_applies_ai_embed_tenant_id_on_customer_queries_schema(): void
    {
        $this->assertTrue(Schema::hasTable('customer_queries'));
        $this->assertTrue(Schema::hasColumn('customer_queries', 'ai_conversation_id'));
        $this->assertTrue(Schema::hasColumn('customer_queries', 'ai_embed_tenant_id'));
        $this->assertFalse(Schema::hasColumn('customer_queries', 'conversation_id'));
    }
}
