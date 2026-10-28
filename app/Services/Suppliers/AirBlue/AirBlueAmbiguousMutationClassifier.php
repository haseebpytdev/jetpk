<?php

namespace App\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;

/**
 * Classifies AirBlue Zapways mutation outcomes for fail-closed retry policy.
 *
 * Ambiguous-after-transmission codes match AirBlueRefundService safety policy.
 * Transmission stage is annotated by AirBlueClient on exception context.
 */
final class AirBlueAmbiguousMutationClassifier
{
    public const CONTEXT_KEY = 'request_transmission_state';

    public const TRANSMISSION_PRE_SEND = 'pre_send';

    public const TRANSMISSION_SENT_OR_UNCERTAIN = 'sent_or_uncertain';

    /**
     * Provider/transport conditions that are ambiguous after a request may have left the process.
     *
     * @var list<string>
     */
    public const AMBIGUOUS_CODES = [
        'supplier_transport_failed',
        'supplier_http_error',
        'soap_fault',
        'supplier_auth_failed',
        'malformed_xml',
        'empty_xml',
        'xml_too_large',
    ];

    public static function isAmbiguousCode(string $normalizedCode): bool
    {
        return in_array(trim($normalizedCode), self::AMBIGUOUS_CODES, true);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function withTransmissionState(array $context, string $state): array
    {
        $context[self::CONTEXT_KEY] = $state;

        return $context;
    }

    /**
     * True when the HTTP request may already have left the process.
     *
     * Prefers AirBlueClient annotation. When the marker is absent (service-local
     * throws or legacy mocks): ambiguous codes and definitive provider_error imply
     * post-send; other codes are treated as pre-send.
     */
    public static function requestMayHaveBeenSent(AirBlueException $exception): bool
    {
        $state = (string) ($exception->context[self::CONTEXT_KEY] ?? '');
        if ($state === self::TRANSMISSION_PRE_SEND) {
            return false;
        }
        if ($state === self::TRANSMISSION_SENT_OR_UNCERTAIN) {
            return true;
        }

        $code = trim((string) $exception->normalizedCode);

        return self::isAmbiguousCode($code) || $code === 'provider_error';
    }

    /**
     * Uncertain only when the mutation may already have left the process and the code is ambiguous.
     */
    public static function isUncertainAfterTransmission(bool $transmitted, string $normalizedCode): bool
    {
        return $transmitted && self::isAmbiguousCode($normalizedCode);
    }

    public static function isUncertainAfterSend(AirBlueException $exception): bool
    {
        return self::isUncertainAfterTransmission(
            self::requestMayHaveBeenSent($exception),
            (string) $exception->normalizedCode,
        );
    }
}
