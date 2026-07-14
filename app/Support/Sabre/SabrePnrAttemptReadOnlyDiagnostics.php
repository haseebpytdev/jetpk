<?php

namespace App\Support\Sabre;

use App\Models\SupplierBookingAttempt;
use App\Support\Security\SensitiveDataRedactor;

/**
 * V25-CPNR: Read-only safe diagnostics from supplier_booking_attempt rows (no raw payload / PII).
 */
final class SabrePnrAttemptReadOnlyDiagnostics
{
    /** @var list<string> */
    private const FORBIDDEN_KEY_SUBSTRINGS = [
        'raw_payload', 'request_body', 'response_body', 'password', 'secret', 'credential',
        'passport', 'email', 'phone', 'first_name', 'last_name', 'givenname', 'surname',
        'personname', 'contactnumbers', 'document', 'createpassengernamerecordrq', 'pcc', 'token',
    ];

    /**
     * @return array<string, mixed>
     */
    public function summarizeAttempt(SupplierBookingAttempt $attempt): array
    {
        $attempt->loadMissing(['booking']);
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $structured = is_array($safe['safe_validation_excerpts_structured'] ?? null)
            ? array_slice($safe['safe_validation_excerpts_structured'], 0, 6)
            : [];
        $legacyExcerpts = is_array($safe['safe_validation_excerpts'] ?? null)
            ? array_slice($safe['safe_validation_excerpts'], 0, 6)
            : [];
        $qualifierDigest = is_array($safe['v25_airprice_pricing_qualifiers_digest'] ?? null)
            ? $safe['v25_airprice_pricing_qualifiers_digest']
            : (is_array($safe['passenger_records_payload_digest']['airprice_digest'] ?? null)
                ? []
                : []);

        $out = [
            'attempt_id' => $attempt->id,
            'booking_id' => $attempt->booking_id,
            'action' => (string) $attempt->action,
            'status' => (string) $attempt->status,
            'error_code' => $attempt->error_code !== null ? (string) $attempt->error_code : null,
            'error_message_excerpt' => $attempt->error_message !== null
                ? substr((string) $attempt->error_message, 0, 240)
                : null,
            'http_status' => isset($safe['http_status']) ? $safe['http_status'] : null,
            'live_call_attempted' => (bool) ($safe['live_call_attempted'] ?? false),
            'pnr_attempted' => ($safe['live_call_attempted'] ?? false) === true,
            'endpoint_path' => isset($safe['endpoint_path']) ? (string) $safe['endpoint_path'] : null,
            'payload_schema' => isset($safe['payload_schema']) ? (string) $safe['payload_schema'] : null,
            'selected_payload_style' => isset($safe['selected_payload_style'])
                ? (string) $safe['selected_payload_style']
                : (isset($safe['create_payload_style']) ? (string) $safe['create_payload_style'] : null),
            'cpnr_schema_validation_pointer' => isset($safe['cpnr_schema_validation_pointer'])
                ? (string) $safe['cpnr_schema_validation_pointer']
                : null,
            'cpnr_schema_validation_message_summary' => isset($safe['cpnr_schema_validation_message_summary'])
                ? (string) $safe['cpnr_schema_validation_message_summary']
                : null,
            'safe_validation_excerpts_structured' => $structured !== [] ? $structured : null,
            'safe_validation_excerpts' => $legacyExcerpts !== [] ? $legacyExcerpts : null,
            'v25_airprice_pricing_qualifiers_digest' => is_array($safe['v25_airprice_pricing_qualifiers_digest'] ?? null)
                ? $safe['v25_airprice_pricing_qualifiers_digest']
                : null,
            'ticket_issuance_attempted' => (bool) ($safe['ticket_issuance_attempted'] ?? false),
            'airticket_attempted' => (bool) ($safe['airticket_attempted'] ?? false),
        ];

        return SensitiveDataRedactor::redact($this->stripForbiddenKeys($out));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripForbiddenKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $lower = strtolower($key);
            $blocked = false;
            foreach (self::FORBIDDEN_KEY_SUBSTRINGS as $needle) {
                if (str_contains($lower, $needle)) {
                    $blocked = true;
                    break;
                }
            }
            if ($blocked) {
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->stripForbiddenKeys($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
