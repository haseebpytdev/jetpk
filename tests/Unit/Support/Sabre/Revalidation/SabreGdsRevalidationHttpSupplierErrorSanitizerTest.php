<?php

namespace Tests\Unit\Support\Sabre\Revalidation;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationHttpSupplierErrorSanitizer;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationSanitizedOutcomeContract;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationOutcomeMapper;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SabreGdsRevalidationHttpSupplierErrorSanitizerTest extends TestCase
{
    private SabreGdsRevalidationHttpSupplierErrorSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new SabreGdsRevalidationHttpSupplierErrorSanitizer;
    }

    public function test_http_400_with_type_error_code_and_message(): void
    {
        $result = $this->sanitizer->extract(400, [
            'status' => 'Incomplete',
            'type' => 'Validation',
            'errorCode' => 'ERR.2SG.INVALID.REQUEST',
            'message' => 'Revalidation request failed validation.',
        ]);

        $this->assertSame('Validation', $result['supplier_error_type']);
        $this->assertSame('ERR.2SG.INVALID.REQUEST', $result['supplier_error_code']);
        $this->assertStringContainsString('validation', strtolower((string) $result['supplier_error_message_safe']));
        $this->assertTrue($result['response_json_valid']);
        $this->assertFalse($result['automatic_retry_allowed']);
        $this->assertFalse($result['same_payload_retry_recommended']);
    }

    public function test_http_400_with_additional_messages(): void
    {
        $result = $this->sanitizer->extract(400, [
            'type' => 'Application',
            'errorCode' => 'ERR.REVALIDATE.BASE',
            'message' => 'Base revalidation error',
            'additionalMessages' => [
                ['type' => 'Warning', 'errorCode' => 'WARN.ONE', 'message' => 'First warning'],
                ['type' => 'Error', 'errorCode' => 'ERR.TWO', 'message' => 'Second error'],
            ],
        ]);

        $this->assertSame('ERR.REVALIDATE.BASE', $result['supplier_error_code']);
        $this->assertSame(['WARN.ONE', 'ERR.TWO'], $result['supplier_additional_message_codes']);
        $this->assertStringContainsString('First warning', (string) $result['supplier_additional_messages_summary']);
        $this->assertSame(2, $result['supplier_error_count']);
        $this->assertSame(1, $result['supplier_warning_count']);
    }

    public function test_nested_validation_paths_are_extracted(): void
    {
        $result = $this->sanitizer->extract(400, [
            'errors' => [[
                'code' => 'BAD_REQUEST',
                'field' => 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0',
                'message' => 'Origin destination information is invalid',
            ]],
        ], null, [
            'response_validation_paths' => ['OTA_AirLowFareSearchRQ.TravelPreferences'],
        ]);

        $this->assertContains(
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0',
            $result['supplier_validation_paths'] ?? [],
        );
        $this->assertContains(
            'OTA_AirLowFareSearchRQ.TravelPreferences',
            $result['supplier_validation_paths'] ?? [],
        );
        $this->assertSame(
            SabreGdsRevalidationHttpSupplierErrorSanitizer::CLASSIFICATION_REQUEST_VALIDATION_FAILED,
            $result['supplier_http_failure_classification'],
        );
    }

    public function test_token_and_credential_redaction(): void
    {
        $result = $this->sanitizer->extract(400, [
            'errorCode' => 'ERR.AUTH',
            'message' => 'Bearer abc123supersecrettoken failed for client_secret=leak',
        ]);

        $message = (string) ($result['supplier_error_message_safe'] ?? '');
        $this->assertStringNotContainsString('abc123supersecrettoken', $message);
        $this->assertStringNotContainsString('client_secret=leak', $message);
        $this->assertStringContainsString('[REDACTED]', $message);
    }

    public function test_passenger_contact_and_document_redaction(): void
    {
        $result = $this->sanitizer->extract(400, [
            'errorCode' => 'ERR.PII',
            'message' => 'Passenger Mr John Smith with passport AB1234567 and email john.doe@example.com rejected',
        ]);

        $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('john.doe@example.com', $encoded);
        $this->assertStringNotContainsString('AB1234567', $encoded);
        $this->assertStringNotContainsString('John Smith', $encoded);
    }

    public function test_bounded_string_and_array_lengths(): void
    {
        $longMessage = str_repeat('A', 500);
        $manyAdditional = [];
        for ($i = 0; $i < 20; $i++) {
            $manyAdditional[] = [
                'type' => 'Error',
                'errorCode' => 'ERR.'.$i,
                'message' => 'Message '.$i.' '.str_repeat('x', 300),
            ];
        }

        $result = $this->sanitizer->extract(400, [
            'errorCode' => 'ERR.BASE',
            'message' => $longMessage,
            'additionalMessages' => $manyAdditional,
        ]);

        $this->assertLessThanOrEqual(280, mb_strlen((string) $result['supplier_error_message_safe']));
        $this->assertLessThanOrEqual(8, count($result['supplier_additional_message_codes'] ?? []));
        $this->assertLessThanOrEqual(280, mb_strlen((string) $result['supplier_additional_messages_summary']));
    }

    public function test_malformed_error_body_stays_generic(): void
    {
        $result = $this->sanitizer->extract(400, null, 'not-json-body');

        $this->assertFalse($result['response_json_valid']);
        $this->assertSame(
            SabreGdsRevalidationHttpSupplierErrorSanitizer::CLASSIFICATION_HTTP_REJECTED,
            $result['supplier_http_failure_classification'],
        );
        $this->assertArrayNotHasKey('supplier_error_code', $result);
    }

    public function test_valid_json_error_body_sets_response_json_valid(): void
    {
        $result = $this->sanitizer->extract(400, ['errorCode' => 'ERR.TEST', 'message' => 'Known failure']);

        $this->assertTrue($result['response_json_valid']);
        $this->assertSame('ERR.TEST', $result['supplier_error_code']);
    }

    public function test_non_json_http_rejection(): void
    {
        $result = $this->sanitizer->extract(502, null, '<html>upstream</html>');

        $this->assertFalse($result['response_json_valid']);
        $this->assertSame(
            SabreGdsRevalidationHttpSupplierErrorSanitizer::CLASSIFICATION_HTTP_REJECTED,
            $result['supplier_http_failure_classification'],
        );
    }

    #[DataProvider('classificationProvider')]
    public function test_exact_safe_classification_when_supported(array $body, string $expected): void
    {
        $result = $this->sanitizer->extract(400, $body);

        $this->assertSame($expected, $result['supplier_http_failure_classification']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function classificationProvider(): array
    {
        return [
            'request_validation' => [[
                'errorCode' => 'BAD_REQUEST',
                'message' => 'Request validation failed for field',
            ], SabreGdsRevalidationHttpSupplierErrorSanitizer::CLASSIFICATION_REQUEST_VALIDATION_FAILED],
            'schema_rejected' => [[
                'errorCode' => 'ERR.SCHEMA',
                'message' => 'Malformed JSON schema in request body',
            ], SabreGdsRevalidationHttpSupplierErrorSanitizer::CLASSIFICATION_SCHEMA_REJECTED],
            'endpoint_style_mismatch' => [[
                'errorCode' => 'ERR.ENDPOINT',
                'message' => 'Endpoint not found for payload style',
            ], SabreGdsRevalidationHttpSupplierErrorSanitizer::CLASSIFICATION_ENDPOINT_STYLE_MISMATCH],
            'invalid_reference_linkage' => [[
                'errorCode' => '27131',
                'message' => 'Missing pricingInformation linkage reference',
            ], SabreGdsRevalidationHttpSupplierErrorSanitizer::CLASSIFICATION_INVALID_REFERENCE_LINKAGE],
            'unsupported_element' => [[
                'errorCode' => 'ERR.UNSUPPORTED',
                'message' => 'Unsupported element in request',
            ], SabreGdsRevalidationHttpSupplierErrorSanitizer::CLASSIFICATION_UNSUPPORTED_ELEMENT],
            'generic_http_rejected' => [[
                'errorCode' => 'ERR.GENERIC',
                'message' => 'Supplier rejected request',
            ], SabreGdsRevalidationHttpSupplierErrorSanitizer::CLASSIFICATION_HTTP_REJECTED],
        ];
    }

    public function test_mapper_uses_specific_classification_over_generic_http_rejected(): void
    {
        $mapper = new SabreGdsLiveScenarioRevalidationOutcomeMapper;
        $supplier = $this->sanitizer->extract(400, [
            'errorCode' => 'ERR.UNSUPPORTED',
            'message' => 'Unsupported element in TravelPreferences',
        ]);
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap(array_merge([
            'success' => false,
            'http_status' => 400,
            'reason_code' => 'sabre_revalidation_failed',
            'revalidation_failure_class' => 'http_rejected',
            'response_structure' => [
                'top_level_keys' => 'errorCode,message',
                'key_paths' => '',
                'empty_body' => 'false',
                'json_valid' => 'true',
                'candidate_fields' => '',
                'candidate_count' => '0',
            ],
        ], $supplier), true, true);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_UNSUPPORTED_ELEMENT,
            $mapper->classifyScenarioReasonCode($outcome),
        );
        $this->assertFalse($outcome['automatic_retry_allowed']);
        $this->assertFalse($outcome['same_payload_retry_recommended']);
    }

    public function test_correlated_log_emits_without_raw_bodies(): void
    {
        Log::spy();

        $this->sanitizer->emitCorrelatedLog([
            'run_id' => 'bb9c6e5c-ed67-45bb-bd6b-34b3cfee65a1',
            'search_correlation_id' => 'search-1',
            'revalidation_correlation_id' => '3253487a-0475-4c4d-bccb-aae6c9d01fd6',
            'endpoint_path' => '/v4/shop/flights/revalidate',
            'payload_style' => 'iati_like_bfm_revalidate_v1',
            'http_status' => 400,
            'supplier_error_code' => 'ERR.TEST',
            'supplier_error_message_safe' => 'Safe summary only',
            'supplier_http_failure_classification' => SabreGdsRevalidationHttpSupplierErrorSanitizer::CLASSIFICATION_HTTP_REJECTED,
            'response_candidate_count' => 0,
            'supplier_revalidation_call_count' => 1,
        ]);

        Log::shouldHaveReceived('notice')
            ->once()
            ->with('sabre.revalidate.http_supplier_error', \Mockery::on(function (array $context): bool {
                return ($context['run_id'] ?? null) === 'bb9c6e5c-ed67-45bb-bd6b-34b3cfee65a1'
                    && ($context['supplier_revalidation_call_count'] ?? null) === 1
                    && ! isset($context['raw_body'])
                    && ! isset($context['response_body']);
            }));
    }
}
