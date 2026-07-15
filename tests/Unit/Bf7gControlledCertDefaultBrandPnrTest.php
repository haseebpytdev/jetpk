<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class Bf7gControlledCertDefaultBrandPnrTest extends TestCase
{
    /**
     * @return array{exit_code: int, json: array<string, mixed>|null, raw: string}
     */
    private function runBf7gScript(string ...$args): array
    {
        $script = base_path('scripts/bf7g-controlled-cert-default-brand-pnr.php');
        $argStr = implode(' ', array_map(static fn (string $a): string => escapeshellarg($a), $args));
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.$argStr;
        $raw = (string) shell_exec($cmd);
        $json = json_decode($raw, true);
        if (! is_array($json) && preg_match('/\{[\s\S]*\}\s*$/', $raw, $matches) === 1) {
            $json = json_decode($matches[0], true);
        }
        $exitCode = 1;
        if (is_array($json) && in_array($json['status'] ?? '', ['live_attempted', 'inspect_only'], true)) {
            $exitCode = 0;
        } elseif (is_array($json)) {
            $exitCode = 1;
        }

        return [
            'exit_code' => $exitCode,
            'json' => is_array($json) ? $json : null,
            'raw' => $raw,
        ];
    }

    public function test_cli_self_test_parse_exits_zero(): void
    {
        $script = base_path('scripts/bf7g-controlled-cert-default-brand-pnr.php');
        $this->assertFileExists($script);

        $cmd = PHP_BINARY.' '.escapeshellarg($script).' --self-test-cli-parse 2>&1';
        exec($cmd, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_parse_bf7g_argv_booking_forms(): void
    {
        require_once base_path('scripts/bf7g-controlled-cert-default-brand-pnr.php');

        $parsed = parseBf7gArgv(['script', '--booking=51']);
        $this->assertSame(51, $parsed['booking_id']);

        $parsed = parseBf7gArgv(['script', '--booking', '51']);
        $this->assertSame(51, $parsed['booking_id']);
    }

    public function test_production_env_gate_blocks_without_allow_flag(): void
    {
        require_once base_path('scripts/bf7g-controlled-cert-default-brand-pnr.php');

        $this->assertNotNull(resolveBf7gAppEnvGate(false, 'production'));
        $this->assertNull(resolveBf7gAppEnvGate(true, 'production'));
        $this->assertNull(resolveBf7gAppEnvGate(false, 'local'));
    }

    public function test_blocked_booking_ids_are_43_and_46(): void
    {
        foreach ([43, 46] as $blockedId) {
            $result = $this->runBf7gScript('--booking='.$blockedId, '--skip-send');
            $this->assertSame(1, $result['exit_code'], $result['raw']);
            $this->assertIsArray($result['json'], $result['raw']);
            $this->assertSame('blocked_booking_id_43_or_46', $result['json']['sabre_classification'] ?? null);
        }
    }

    public function test_missing_booking_blocks_before_send(): void
    {
        $result = $this->runBf7gScript('--skip-send');
        $this->assertSame(1, $result['exit_code'], $result['raw']);
        $this->assertIsArray($result['json'], $result['raw']);
        $this->assertStringContainsString('Missing --booking', (string) ($result['json']['error'] ?? ''));
    }

    public function test_production_default_brand_diagnostics_helper(): void
    {
        require_once base_path('scripts/bf7g-controlled-cert-default-brand-pnr.php');

        $this->assertNull(bf7gAssertProductionDefaultBrandDiagnostics([
            'compare_gate_enabled' => false,
            'active_brand_shape_selector' => SabreBookingPayloadBuilder::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR,
            'default_brand_node_shape' => 'array_of_content_objects',
            'current_brand_node_shape' => 'array_of_content_objects',
        ]));

        $this->assertSame(
            'compare_gate_must_be_off',
            bf7gAssertProductionDefaultBrandDiagnostics(['compare_gate_enabled' => true])
        );
        $this->assertSame(
            'active_brand_shape_selector_not_object_content',
            bf7gAssertProductionDefaultBrandDiagnostics([
                'compare_gate_enabled' => false,
                'active_brand_shape_selector' => 'current_object_code',
                'default_brand_node_shape' => 'array_of_content_objects',
                'current_brand_node_shape' => 'array_of_content_objects',
            ])
        );
    }

    public function test_flags_restored_after_exception(): void
    {
        require_once base_path('scripts/bf7g-controlled-cert-default-brand-pnr.php');

        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false);
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false);
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false);
        Config::set('suppliers.sabre.branded_fares_probe_enabled', false);

        $before = bf7gFlagSnapshot();
        $original = $before;

        try {
            setBf7gProductionDefaultFlags();
            $during = bf7gFlagSnapshot();
            $this->assertFalse($during['SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED']);
            $this->assertFalse($during['SABRE_TICKETING_ENABLED']);
            throw new \RuntimeException('simulated failure');
        } catch (\RuntimeException) {
            bf7gApplyFlagSnapshot($original);
        }

        $after = bf7gFlagSnapshot();
        $this->assertTrue(bf7gFlagsRestored($before, $after));
        $this->assertFalse(config('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled'));
        $this->assertFalse(config('suppliers.sabre.ticketing_enabled'));
    }
}
