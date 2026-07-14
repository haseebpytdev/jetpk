<?php

namespace Tests\Unit\Support;

use App\Support\Security\SensitiveDataRedactor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SensitiveDataRedactorTest extends TestCase
{
    #[Test]
    public function redact_removes_payment_card_fields(): void
    {
        $input = [
            'card_number' => '4111111111111111',
            'cvv' => '123',
            'pan' => '4111111111111111',
            'payment_method' => 'card',
        ];

        $redacted = SensitiveDataRedactor::redact($input);

        foreach (array_keys($input) as $key) {
            $this->assertSame('[REDACTED]', $redacted[$key], "Expected {$key} to be redacted");
        }
    }

    #[Test]
    public function redact_removes_authorization_bearer_token_in_strings(): void
    {
        $input = 'Request failed Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.payload.sig';

        $this->assertSame(
            'Request failed Authorization: Bearer [REDACTED]',
            SensitiveDataRedactor::redact($input),
        );
    }

    #[Test]
    public function redact_removes_password_client_secret_and_token_fields(): void
    {
        $input = [
            'password' => 'secret-pass',
            'client_secret' => 'cs_live',
            'access_token' => 'tok_abc',
            'meta' => ['refresh_token' => 'rt_123'],
        ];

        $redacted = SensitiveDataRedactor::redact($input);

        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['client_secret']);
        $this->assertSame('[REDACTED]', $redacted['access_token']);
        $this->assertSame('[REDACTED]', $redacted['meta']['refresh_token']);
    }

    #[Test]
    public function redact_removes_passenger_pii_fields(): void
    {
        $input = [
            'email' => 'pax@example.com',
            'phone_number' => '+923001234567',
            'passport_number' => 'AB1234567',
            'date_of_birth' => '1990-01-01',
            'given_name' => 'Ali',
            'surname' => 'Khan',
            'name' => 'Ali Khan',
        ];

        $redacted = SensitiveDataRedactor::redact($input);

        foreach (array_keys($input) as $key) {
            $this->assertSame('[REDACTED]', $redacted[$key], "Expected {$key} to be redacted");
        }
    }

    #[Test]
    public function sanitize_supplier_summary_strips_raw_payload_keys_and_truncates(): void
    {
        $unsafe = str_repeat('X', 300);
        $summary = [
            'http_status' => 400,
            'request_payload' => ['CreatePassengerNameRecordRQ' => ['passengers' => []]],
            'response_payload' => ['errors' => ['detail' => 'fail']],
            'reason' => 'sabre_booking_failed',
            'probable_issue' => $unsafe,
            'email' => 'hidden@example.com',
        ];

        $sanitized = SensitiveDataRedactor::sanitizeSupplierSummary($summary);

        $this->assertArrayHasKey('http_status', $sanitized);
        $this->assertArrayHasKey('reason', $sanitized);
        $this->assertArrayNotHasKey('request_payload', $sanitized);
        $this->assertArrayNotHasKey('response_payload', $sanitized);
        $this->assertSame('[REDACTED]', $sanitized['email']);
        $this->assertLessThanOrEqual(240, strlen((string) $sanitized['probable_issue']));
        $this->assertStringEndsWith('...', (string) $sanitized['probable_issue']);
    }

    #[Test]
    public function sanitize_error_message_redacts_tokens_and_truncates(): void
    {
        $message = 'Sabre error Bearer abc.def.ghi for user pax@example.com — '.str_repeat('z', 300);

        $sanitized = SensitiveDataRedactor::sanitizeErrorMessage($message);

        $this->assertStringContainsString('Bearer [REDACTED]', (string) $sanitized);
        $this->assertStringContainsString('[REDACTED_EMAIL]', (string) $sanitized);
        $this->assertLessThanOrEqual(240, strlen((string) $sanitized));
    }

    #[Test]
    public function supplier_safe_context_keeps_only_allowed_keys(): void
    {
        $context = SensitiveDataRedactor::supplierSafeContext([
            'booking_id' => 42,
            'provider' => 'sabre',
            'http_status' => 401,
            'authorization' => 'Bearer secret',
            'passengers' => [['email' => 'x@y.com']],
            'raw_body' => '{"secret":true}',
        ]);

        $this->assertSame([
            'booking_id' => 42,
            'provider' => 'sabre',
            'http_status' => 401,
        ], $context);
    }

    #[Test]
    public function prepare_supplier_attempt_attributes_nulls_payloads_on_failed_status(): void
    {
        $prepared = SensitiveDataRedactor::prepareSupplierAttemptAttributes([
            'status' => 'failed',
            'safe_summary' => ['reason' => 'test', 'request_payload' => ['x' => 1]],
            'error_message' => 'Bearer leaked.token.value',
            'request_payload' => ['password' => 'x'],
            'response_payload' => ['access_token' => 'y'],
        ]);

        $this->assertNull($prepared['request_payload']);
        $this->assertNull($prepared['response_payload']);
        $this->assertArrayNotHasKey('request_payload', $prepared['safe_summary']);
        $this->assertStringContainsString('Bearer [REDACTED]', (string) $prepared['error_message']);
    }
}
