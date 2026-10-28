<?php

namespace App\Services\Suppliers\AirBlue;

/**
 * Classifies AirBlue Zapways mutation outcomes for fail-closed retry policy.
 *
 * Ambiguous-after-transmission codes match AirBlueRefundService safety policy.
 */
final class AirBlueAmbiguousMutationClassifier
{
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
     * Uncertain only when the mutation may already have been accepted by the provider.
     */
    public static function isUncertainAfterTransmission(bool $transmitted, string $normalizedCode): bool
    {
        return $transmitted && self::isAmbiguousCode($normalizedCode);
    }
}
