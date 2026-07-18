<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use Tests\TestCase;

#[RunClassInSeparateProcess]
class Bf7dControlledCertBrandVariantTest extends TestCase
{
    /**
     * @return array{exit_code: int, json: array<string, mixed>|null, raw: string}
     */
    private function runBf7dScript(string ...$args): array
    {
        $script = base_path('scripts/bf7d-controlled-cert-brand-variant.php');
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
        $script = base_path('scripts/bf7d-controlled-cert-brand-variant.php');
        $this->assertFileExists($script);

        $cmd = PHP_BINARY.' '.escapeshellarg($script).' --self-test-cli-parse 2>&1';
        exec($cmd, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_parse_bf7d_argv_booking_and_variant_forms(): void
    {
        require_once base_path('scripts/bf7d-controlled-cert-brand-variant.php');

        $parsed = parseBf7dArgv(['script', '--booking=51', '--variant=object_value']);
        $this->assertSame(51, $parsed['booking_id']);
        $this->assertSame('object_value', $parsed['variant']);

        $parsed = parseBf7dArgv(['script', '--booking', '51', '--variant', 'object_value']);
        $this->assertSame(51, $parsed['booking_id']);
        $this->assertSame('object_value', $parsed['variant']);
    }

    public function test_production_env_gate_blocks_without_allow_flag(): void
    {
        require_once base_path('scripts/bf7d-controlled-cert-brand-variant.php');

        $this->assertNotNull(resolveAppEnvGate(false, 'production'));
        $this->assertNull(resolveAppEnvGate(true, 'production'));
        $this->assertNull(resolveAppEnvGate(false, 'local'));
    }

    public function test_blocked_booking_ids_are_43_and_46(): void
    {
        foreach ([43, 46] as $blockedId) {
            $result = $this->runBf7dScript('--booking='.$blockedId, '--variant=object_value', '--skip-send');
            $this->assertSame(1, $result['exit_code'], $result['raw']);
            $this->assertIsArray($result['json'], $result['raw']);
            $this->assertSame('blocked_booking_id_43_or_46', $result['json']['sabre_classification'] ?? null);
        }
    }

    public function test_missing_booking_blocks_before_send(): void
    {
        $result = $this->runBf7dScript('--variant=object_value', '--skip-send');
        $this->assertSame(1, $result['exit_code'], $result['raw']);
        $this->assertIsArray($result['json'], $result['raw']);
        $this->assertStringContainsString('Missing --booking', (string) ($result['json']['error'] ?? ''));
    }

    public function test_missing_variant_blocks_before_send(): void
    {
        $result = $this->runBf7dScript('--booking=51', '--skip-send');
        $this->assertSame(1, $result['exit_code'], $result['raw']);
        $this->assertIsArray($result['json'], $result['raw']);
        $this->assertStringContainsString('Missing --variant', (string) ($result['json']['error'] ?? ''));
    }

    public function test_unknown_variant_blocks_before_send(): void
    {
        $result = $this->runBf7dScript('--booking=51', '--variant=not_real', '--skip-send');
        $this->assertSame(1, $result['exit_code'], $result['raw']);
        $this->assertIsArray($result['json'], $result['raw']);
        $this->assertSame('unknown_variant', $result['json']['sabre_classification'] ?? null);
        $this->assertStringContainsString('Unknown variant', (string) ($result['json']['error'] ?? ''));
    }

    public function test_flags_restored_after_exception(): void
    {
        require_once base_path('scripts/bf7d-controlled-cert-brand-variant.php');

        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false);
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false);
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false);
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_variant', 'current_object_code');
        Config::set('suppliers.sabre.branded_fares_probe_enabled', false);

        $before = flagSnapshot();
        $original = $before;

        try {
            setBf7dCompareTestFlags('object_value');
            $during = flagSnapshot();
            $this->assertTrue($during['SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED']);
            $this->assertSame('object_value', $during['SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_VARIANT']);
            $this->assertFalse($during['SABRE_TICKETING_ENABLED']);
            throw new \RuntimeException('simulated failure');
        } catch (\RuntimeException) {
            applyFlagSnapshot($original);
        }

        $after = flagSnapshot();
        $this->assertTrue(flagsRestored($before, $after));
        $this->assertFalse(config('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled'));
        $this->assertFalse(config('suppliers.sabre.ticketing_enabled'));
    }
}
