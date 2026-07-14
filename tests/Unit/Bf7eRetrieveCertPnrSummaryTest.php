<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class Bf7eRetrieveCertPnrSummaryTest extends TestCase
{
    public function test_cli_self_test_parse_exits_zero(): void
    {
        $script = base_path('scripts/bf7e-retrieve-cert-pnr-summary.php');
        $this->assertFileExists($script);

        $cmd = PHP_BINARY.' '.escapeshellarg($script).' --self-test-cli-parse 2>&1';
        exec($cmd, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_parse_bf7e_argv_forms(): void
    {
        require_once base_path('scripts/bf7e-retrieve-cert-pnr-summary.php');

        $parsed = parseBf7eArgv(['script', '--pnr=RQFUYD', '--booking=51']);
        $this->assertSame('RQFUYD', $parsed['pnr']);
        $this->assertSame(51, $parsed['booking_id']);

        $parsed = parseBf7eArgv(['script', '--pnr', 'QJUAKV', '--booking', '51']);
        $this->assertSame('QJUAKV', $parsed['pnr']);
        $this->assertSame(51, $parsed['booking_id']);
        $this->assertTrue($parsed['allow_production_cert_controlled_retrieve'] === false);
    }

    public function test_resolve_app_env_gate(): void
    {
        require_once base_path('scripts/bf7e-retrieve-cert-pnr-summary.php');

        $this->assertNull(resolveAppEnvGate(false, 'local'));
        $this->assertNull(resolveAppEnvGate(true, 'production'));
        $this->assertNotNull(resolveAppEnvGate(false, 'production'));
    }

    public function test_extract_fare_context_finds_branded_fields_without_pii(): void
    {
        require_once base_path('scripts/bf7e-retrieve-cert-pnr-summary.php');

        $json = [
            'TravelItinerary' => [
                'ItineraryInfo' => [
                    'ReservationItems' => [
                        'Item' => [
                            [
                                'FlightSegment' => [
                                    'MarketingAirline' => ['Code' => 'SV'],
                                    'ResBookDesigCode' => 'V',
                                    'FareBasis' => ['Code' => 'VOWFL/V'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'Brand' => [['content' => 'FL']],
            'fareFamilyName' => 'FREEDOM',
            'baggageAllowance' => '30 KG',
            'travelers' => [
                ['givenName' => 'JANESECRET', 'surname' => 'DOESECRET', 'email' => 'secret@example.com'],
            ],
            'TotalFare' => ['Amount' => '88584', 'CurrencyCode' => 'SAR'],
        ];

        $ctx = bf7eExtractFareContext($json);

        $this->assertContains('VOWFL/V', $ctx['fare_basis_codes']);
        $this->assertContains('FL', $ctx['brand_or_family']);
        $this->assertContains('FREEDOM', $ctx['brand_or_family']);
        $this->assertContains('V', $ctx['booking_classes']);
        $this->assertNotEmpty($ctx['baggage_hints']);
        $this->assertNotEmpty($ctx['price_hints']);

        $encoded = json_encode($ctx, JSON_UNESCAPED_SLASHES);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('JANESECRET', $encoded);
        $this->assertStringNotContainsString('secret@example.com', $encoded);
    }

    public function test_compute_expected_match(): void
    {
        require_once base_path('scripts/bf7e-retrieve-cert-pnr-summary.php');

        $expected = [
            'brand_code' => 'FL',
            'fare_family_name' => 'FREEDOM',
            'fare_basis' => 'VOWFL/V',
            'booking_class' => 'V',
            'baggage' => '30 KG',
        ];
        $fareContext = [
            'fare_basis_codes' => ['VOWFL/V'],
            'brand_or_family' => ['FL', 'FREEDOM'],
            'booking_classes' => ['V'],
            'baggage_hints' => ['30 KG'],
            'price_hints' => [],
        ];
        $segments = [
            ['booking_class' => 'V', 'carrier' => 'SV'],
        ];

        $match = bf7eComputeExpectedMatch($expected, $fareContext, $segments);

        $this->assertTrue($match['brand_code_matches']);
        $this->assertTrue($match['fare_family_name_matches']);
        $this->assertTrue($match['fare_basis_matches']);
        $this->assertTrue($match['booking_class_matches']);
        $this->assertTrue($match['baggage_matches']);
    }

    public function test_blocked_booking_id_returns_error_without_http(): void
    {
        $script = base_path('scripts/bf7e-retrieve-cert-pnr-summary.php');
        Config::set('app.env', 'testing');

        $cmd = PHP_BINARY.' '.escapeshellarg($script).' --pnr=RQFUYD --booking=43 --skip-send 2>&1';
        $raw = (string) shell_exec($cmd);
        $json = json_decode($raw, true);

        $this->assertIsArray($json);
        $this->assertSame('error', $json['status'] ?? null);
        $this->assertStringContainsString('booking_id_blocked_for_bf7e', (string) ($json['error'] ?? ''));
    }

    public function test_assert_safety_flags_off_blocks_when_ticketing_enabled(): void
    {
        require_once base_path('scripts/bf7e-retrieve-cert-pnr-summary.php');

        Config::set('suppliers.sabre.ticketing_enabled', true);

        $this->assertSame('sabre_ticketing_enabled_must_be_false', assertSafetyFlagsOff());

        Config::set('suppliers.sabre.ticketing_enabled', false);
    }
}
