<?php

namespace Tests\Unit\Support\Sabre;

use App\Support\Sabre\SabreCapabilityPosture;
use Tests\TestCase;

class SabreCapabilityPostureTest extends TestCase
{
    private SabreCapabilityPosture $posture;

    /** @var list<string> */
    private const FORBIDDEN_FRAGMENTS = [
        'password',
        'client_secret',
        'access_token',
        'sabre_cert_',
        'sabre_6md8',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->posture = new SabreCapabilityPosture;
    }

    public function test_gds_cancel_is_implemented_env_gated_not_unresolved(): void
    {
        $cancel = $this->posture->cancelPosture();

        $this->assertSame('gds_cancel', $cancel['key']);
        $this->assertSame('enabled', $cancel['status']);
        $this->assertFalse($cancel['manual_required']);
        $this->assertFalse($cancel['production_allowed']);
        $this->assertFalse($cancel['live_supplier_call_allowed']);
        $this->assertSame('Enabled', $this->posture->architectureDisplayLabel($cancel));
    }

    public function test_gds_ticketing_is_implemented_env_gated_not_disabled(): void
    {
        $ticketing = $this->posture->ticketingPosture();

        $this->assertSame('gds_ticketing', $ticketing['key']);
        $this->assertSame('enabled', $ticketing['status']);
        $this->assertFalse($ticketing['manual_required']);
        $this->assertFalse($ticketing['production_allowed']);
        $this->assertSame('Enabled', $this->posture->architectureDisplayLabel($ticketing));
    }

    public function test_ndc_keys_reflect_implemented_env_gated_posture(): void
    {
        $ndc = $this->posture->ndcPosture();

        $this->assertSame('Unknown/disabled — not production', $ndc['summary_label']);
        $this->assertCount(4, $ndc['items']);

        $byKey = collect($ndc['items'])->keyBy('key');
        $this->assertSame('enabled', $byKey['ndc_order_create']['status']);
        $this->assertSame('enabled', $byKey['ndc_order_retrieve']['status']);
        $this->assertSame('disabled', $byKey['ndc_cancel']['status']);
        $this->assertFalse($byKey['ndc_order_create']['production_allowed']);
    }

    public function test_diagnostics_are_diagnostic_only(): void
    {
        $diagnostics = $this->posture->diagnosticsPosture();

        $this->assertSame('diagnostics', $diagnostics['key']);
        $this->assertSame('diagnostic_only', $diagnostics['status']);
        $this->assertFalse($diagnostics['production_allowed']);
        $this->assertSame(
            'Diagnostic only — not customer-facing',
            $this->posture->architectureDisplayLabel($diagnostics),
        );
    }

    public function test_summary_for_keys_returns_requested_capabilities(): void
    {
        $items = $this->posture->summaryForKeys(['gds_search', 'gds_cancel']);

        $this->assertCount(2, $items);
        $this->assertSame('gds_search', $items[0]['key']);
        $this->assertSame('enabled', $items[0]['status']);
        $this->assertSame('gds_cancel', $items[1]['key']);
        $this->assertSame('enabled', $items[1]['status']);
    }

    public function test_booking_view_summary_includes_safe_labels(): void
    {
        $summary = $this->posture->bookingViewSummary();

        $this->assertTrue($summary['show']);
        $this->assertSame('Enabled', $summary['gds_cancel_label']);
        $this->assertSame('Enabled', $summary['gds_ticketing_label']);
        $this->assertSame('Unknown/disabled — not production', $summary['ndc_label']);
        $this->assertStringContainsString('not customer-facing', strtolower($summary['diagnostics_label']));
        $this->assertNotEmpty($summary['staff_guidance']);
    }

    public function test_output_contains_no_secret_like_keys(): void
    {
        $encoded = strtolower(json_encode([
            $this->posture->cancelPosture(),
            $this->posture->ticketingPosture(),
            $this->posture->ndcPosture(),
            $this->posture->diagnosticsPosture(),
            $this->posture->bookingViewSummary(),
        ], JSON_THROW_ON_ERROR));

        foreach (self::FORBIDDEN_FRAGMENTS as $fragment) {
            $this->assertStringNotContainsString($fragment, $encoded, "Unexpected secret-like fragment: {$fragment}");
        }
    }
}
