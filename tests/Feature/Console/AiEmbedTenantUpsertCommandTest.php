<?php

namespace Tests\Feature\Console;

use App\Models\AiEmbedTenant;
use App\Support\Ai\Embed\EmbedTenantCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiEmbedTenantUpsertCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_tenant_defaults_to_empty_capabilities(): void
    {
        $this->artisan('ai-embed:tenant-upsert', [
            'slug' => 'client_a',
            '--display-name' => 'Client A',
        ])->assertSuccessful();

        $tenant = AiEmbedTenant::query()->where('slug', 'client_a')->first();
        $this->assertNotNull($tenant);
        $this->assertSame([], $tenant->capabilities);
    }

    public function test_generic_tenant_preserves_explicit_capability_subset(): void
    {
        $this->artisan('ai-embed:tenant-upsert', [
            'slug' => 'client_b',
            '--capability' => [EmbedTenantCapability::GENERAL_AI],
        ])->assertSuccessful();

        $tenant = AiEmbedTenant::query()->where('slug', 'client_b')->first();
        $this->assertNotNull($tenant);
        $this->assertSame([EmbedTenantCapability::GENERAL_AI], $tenant->capabilities);
    }

    public function test_unknown_capabilities_are_rejected_when_none_are_valid(): void
    {
        $this->artisan('ai-embed:tenant-upsert', [
            'slug' => 'client_c',
            '--capability' => ['not_a_real_capability'],
        ])->assertFailed();

        $this->assertNull(AiEmbedTenant::query()->where('slug', 'client_c')->first());
    }

    public function test_unknown_capabilities_are_ignored_when_valid_capabilities_also_provided(): void
    {
        $this->artisan('ai-embed:tenant-upsert', [
            'slug' => 'client_d',
            '--capability' => ['not_a_real_capability', EmbedTenantCapability::KNOWLEDGE],
        ])->assertSuccessful();

        $tenant = AiEmbedTenant::query()->where('slug', 'client_d')->first();
        $this->assertNotNull($tenant);
        $this->assertSame([EmbedTenantCapability::KNOWLEDGE], $tenant->capabilities);
    }

    public function test_jetpakistan_sync_command_sets_full_capability_set(): void
    {
        $this->artisan('ai-embed:tenant-sync-jetpakistan')->assertSuccessful();

        $tenant = AiEmbedTenant::query()->where('slug', 'jetpakistan')->first();
        $this->assertNotNull($tenant);
        $this->assertSame(EmbedTenantCapability::all(), $tenant->capabilities);
    }
}
