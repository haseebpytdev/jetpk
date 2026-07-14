<?php

namespace Tests\Feature\Ui;

use App\Support\Ui\UiLayerRegistry;
use App\Support\Ui\UiLayerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class UiLayerOverrideSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_loads_manifest_layers(): void
    {
        $layers = UiLayerRegistry::all();

        $this->assertNotEmpty($layers);
        $this->assertNotNull(UiLayerRegistry::find('example-public-shell'));
    }

    public function test_resolver_returns_no_layers_when_globally_disabled(): void
    {
        config(['ui-layers.enabled' => false]);

        $paths = app(UiLayerResolver::class)->activeCssPaths(['public']);

        $this->assertSame([], $paths);
    }

    public function test_resolver_loads_enabled_layer_for_context(): void
    {
        config([
            'ui-layers.enabled' => true,
            'ui-layers.layers' => [
                [
                    'key' => 'test-layer',
                    'contexts' => ['public'],
                    'order' => 10,
                    'enabled' => true,
                    'css' => ['css/layers/public/test-layer.css'],
                    'js' => [],
                    'description' => 'test',
                    'rollback' => 'disable test-layer',
                ],
            ],
        ]);

        // Reset static cache after config override
        $ref = new \ReflectionClass(UiLayerRegistry::class);
        $prop = $ref->getProperty('indexed');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $paths = app(UiLayerResolver::class)->activeCssPaths(['public']);

        $this->assertSame(['css/layers/public/test-layer.css'], $paths);
    }

    public function test_supplier_layer_skipped_without_supplier_context(): void
    {
        config([
            'ui-layers.enabled' => true,
            'ui-layers.layers' => [
                [
                    'key' => 'supplier-only',
                    'contexts' => ['admin'],
                    'suppliers' => ['sabre'],
                    'order' => 10,
                    'enabled' => true,
                    'css' => ['css/layers/suppliers/sabre/x.css'],
                    'js' => [],
                    'description' => 'test',
                    'rollback' => 'disable',
                ],
            ],
        ]);

        $ref = new \ReflectionClass(UiLayerRegistry::class);
        $prop = $ref->getProperty('indexed');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $resolver = app(UiLayerResolver::class);

        $this->assertSame([], $resolver->activeCssPaths(['admin']));
        $this->assertSame(
            ['css/layers/suppliers/sabre/x.css'],
            $resolver->activeCssPaths(['admin'], 'sabre'),
        );
    }

    public function test_contexts_for_request_detects_agent_routes(): void
    {
        $contexts = app(UiLayerResolver::class)->contextsForRequest(
            Request::create('/agent/dashboard', 'GET'),
        );

        $this->assertContains('agent', $contexts);
    }
}
