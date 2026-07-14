<?php

namespace Tests\Unit\Services\Suppliers\Sabre\Core;

use App\Services\Suppliers\Sabre\Core\SabreCapabilityMatrixService;
use Tests\TestCase;

class SabreCapabilityMatrixServiceTest extends TestCase
{
    private SabreCapabilityMatrixService $matrix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matrix = new SabreCapabilityMatrixService;
    }

    public function test_all_returns_capabilities_with_posture_columns(): void
    {
        $all = $this->matrix->all();

        $this->assertGreaterThanOrEqual(17, count($all));
        $this->assertArrayHasKey('gds_search', $all);
        $this->assertArrayHasKey('diagnostics', $all);

        foreach ($all as $cap) {
            $this->assertNotEmpty($cap['key']);
            $this->assertNotEmpty($cap['lane']);
            $this->assertNotEmpty($cap['label']);
            $this->assertContains($cap['code_implemented'], ['yes', 'no']);
            $this->assertContains($cap['production'], ['yes', 'env_gated', 'no']);
            $this->assertContains($cap['live_http'], ['yes', 'env_gated', 'no']);
            $this->assertContains($cap['manual'], ['yes', 'no']);
            $this->assertNotEmpty($cap['evidence']);
            $this->assertIsBool($cap['production_allowed']);
            $this->assertIsBool($cap['live_supplier_call_allowed']);
            $this->assertIsBool($cap['manual_required']);
            $this->assertNotEmpty($cap['notes']);
        }
    }

    public function test_gds_search_is_implemented_and_production_allowed(): void
    {
        $cap = $this->matrix->get('gds_search');

        $this->assertNotNull($cap);
        $this->assertSame('yes', $cap['code_implemented']);
        $this->assertTrue($this->matrix->productionAllowed('gds_search'));
        $this->assertTrue($this->matrix->liveSupplierCallAllowed('gds_search'));
        $this->assertTrue($this->matrix->isEnabled('gds_search'));
        $this->assertFalse($this->matrix->requiresManualHandling('gds_search'));
    }

    public function test_gds_pnr_create_is_implemented_but_env_gated(): void
    {
        $cap = $this->matrix->get('gds_pnr_create');

        $this->assertNotNull($cap);
        $this->assertSame('yes', $cap['code_implemented']);
        $this->assertSame('env_gated', $cap['production']);
        $this->assertSame('env_gated', $cap['live_http']);
        $this->assertSame('pending', $cap['evidence']);
        $this->assertSame('sabre:gds-create-pnr-production', $cap['command']);
        $this->assertTrue($this->matrix->isEnabled('gds_pnr_create'));
        $this->assertFalse($this->matrix->productionAllowed('gds_pnr_create'));
    }

    public function test_gds_pnr_retrieve_sync_is_implemented(): void
    {
        $cap = $this->matrix->get('gds_pnr_retrieve_sync');

        $this->assertNotNull($cap);
        $this->assertSame('yes', $cap['code_implemented']);
        $this->assertTrue($this->matrix->isEnabled('gds_pnr_retrieve_sync'));
        $this->assertTrue($this->matrix->productionAllowed('gds_pnr_retrieve_sync'));
        $this->assertTrue($this->matrix->liveSupplierCallAllowed('gds_pnr_retrieve_sync'));
    }

    public function test_gds_cancel_is_implemented_with_pending_cancel_retrieve_evidence(): void
    {
        $cap = $this->matrix->get('gds_cancel');

        $this->assertNotNull($cap);
        $this->assertSame('yes', $cap['code_implemented']);
        $this->assertSame('pending_cancel_retrieve_confirmation', $cap['evidence']);
        $this->assertSame('env_gated', $cap['production']);
        $this->assertSame('sabre:production-cancel-evidence', $cap['command']);
        $this->assertTrue($this->matrix->isEnabled('gds_cancel'));
        $this->assertFalse($this->matrix->requiresManualHandling('gds_cancel'));
    }

    public function test_gds_ticketing_is_implemented_env_gated_not_disabled(): void
    {
        $cap = $this->matrix->get('gds_ticketing');

        $this->assertNotNull($cap);
        $this->assertSame('yes', $cap['code_implemented']);
        $this->assertSame('env_gated', $cap['production']);
        $this->assertSame('pending', $cap['evidence']);
        $this->assertSame('sabre:gds-issue-ticket', $cap['command']);
        $this->assertTrue($this->matrix->isEnabled('gds_ticketing'));
        $this->assertFalse($this->matrix->requiresManualHandling('gds_ticketing'));
    }

    public function test_void_and_refund_are_provider_unsupported_manual(): void
    {
        foreach (['gds_void', 'gds_refund'] as $key) {
            $cap = $this->matrix->get($key);
            $this->assertNotNull($cap, "Missing capability: {$key}");
            $this->assertSame('yes', $cap['code_implemented']);
            $this->assertSame('provider_unsupported_manual', $cap['evidence']);
            $this->assertTrue($this->matrix->requiresManualHandling($key));
        }
    }

    public function test_ndc_implemented_lanes_are_env_gated_not_disabled(): void
    {
        foreach (['ndc_reprice', 'ndc_order_change', 'ndc_order_retrieve', 'ndc_order_create'] as $key) {
            $cap = $this->matrix->get($key);
            $this->assertNotNull($cap, "Missing capability: {$key}");
            $this->assertSame('yes', $cap['code_implemented']);
            $this->assertSame('env_gated', $cap['production']);
            $this->assertSame('pending', $cap['evidence']);
            $this->assertSame('ndc_reprice_order_change_retrieve', $cap['lane']);
        }

        $this->assertSame('ndc_cancel', $this->matrix->get('ndc_cancel')['lane']);
        $this->assertSame('no', $this->matrix->get('ndc_cancel')['code_implemented']);
    }

    public function test_diagnostics_are_diagnostic_only_and_not_production(): void
    {
        $cap = $this->matrix->get('diagnostics');

        $this->assertNotNull($cap);
        $this->assertSame('diagnostic_only', $cap['status']);
        $this->assertSame('diagnostics_probes', $cap['lane']);
        $this->assertFalse($this->matrix->productionAllowed('diagnostics'));
        $this->assertFalse($this->matrix->liveSupplierCallAllowed('diagnostics'));
        $this->assertTrue($this->matrix->isEnabled('diagnostics'));
    }

    public function test_helper_methods_return_correct_filtered_lists(): void
    {
        $pending = $this->matrix->evidencePending();
        $pendingKeys = array_column($pending, 'key');
        $this->assertContains('gds_ticketing', $pendingKeys);
        $this->assertContains('gds_cancel', $pendingKeys);
        $this->assertNotContains('gds_void', $pendingKeys);

        $disabled = $this->matrix->disabled();
        $disabledKeys = array_column($disabled, 'key');
        $this->assertContains('ndc_cancel', $disabledKeys);
        $this->assertNotContains('gds_ticketing', $disabledKeys);
        $this->assertNotContains('gds_search', $disabledKeys);

        $manual = $this->matrix->providerUnsupportedManual();
        $this->assertCount(2, $manual);

        $this->assertNull($this->matrix->get('nonexistent_capability'));
    }
}
