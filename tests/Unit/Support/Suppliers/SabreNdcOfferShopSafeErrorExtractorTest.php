<?php

namespace Tests\Unit\Support\Suppliers;

use App\Support\Suppliers\SabreNdcOfferShopSafeErrorExtractor;
use Tests\TestCase;

class SabreNdcOfferShopSafeErrorExtractorTest extends TestCase
{
    public function test_http_400_with_application_results_extracts_safe_error_code_and_message(): void
    {
        $extractor = app(SabreNdcOfferShopSafeErrorExtractor::class);
        $result = $extractor->extract(400, [
            'OTA_AirLowFareSearchRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Error' => [[
                        'code' => 'ERR.NDC.SHOP.INVALID',
                        'ShortText' => 'NDC shop request is invalid for this PCC.',
                    ]],
                ],
            ],
        ]);

        $this->assertSame(400, $result['http_status']);
        $this->assertSame('application_results', $result['response_shape']);
        $this->assertSame('INCOMPLETE', $result['application_results_status']);
        $this->assertStringContainsString('sabre_app_ERR.NDC.SHOP.INVALID', (string) $result['safe_error_code']);
        $this->assertStringContainsString('invalid for this PCC', (string) $result['safe_error_message']);
        $this->assertStringNotContainsString('secret-token', json_encode($result));
    }

    public function test_http_400_with_validation_body_extracts_paths_only(): void
    {
        $extractor = app(SabreNdcOfferShopSafeErrorExtractor::class);
        $result = $extractor->extract(400, [
            'errors' => [[
                'code' => 'BAD_REQUEST',
                'field' => 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.DataSources.NDC',
                'description' => 'NDC must be enabled when ATPCO is disabled.',
            ]],
        ]);

        $this->assertSame('rest_error', $result['response_shape']);
        $this->assertSame('request_validation', $result['safe_error_family']);
        $this->assertContains(
            'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.DataSources.NDC',
            $result['validation_paths'] ?? [],
        );
        $this->assertStringContainsString('NDC must be enabled', (string) $result['safe_error_message']);
    }

    public function test_http_400_with_non_json_body_does_not_leak_raw_body(): void
    {
        $extractor = app(SabreNdcOfferShopSafeErrorExtractor::class);
        $raw = str_repeat('X', 5000);
        $result = $extractor->extract(400, null, $raw);

        $this->assertSame('non_json', $result['response_shape']);
        $this->assertSame('http_400', $result['safe_error_code']);
        $this->assertLessThanOrEqual(300, mb_strlen((string) $result['safe_error_message']));
        $this->assertStringNotContainsString(str_repeat('X', 4000), (string) $result['safe_error_message']);
    }
}
