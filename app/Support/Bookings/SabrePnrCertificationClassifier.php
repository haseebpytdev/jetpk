<?php

namespace App\Support\Bookings;

/**
 * C1: Certification outcome labels for {@code sabre:certify-pnr} (wraps {@see SabrePnrFailureClassifier}).
 */
final class SabrePnrCertificationClassifier
{
    public const SUCCESS_PNR_CREATED = 'success_pnr_created';

    public const PNR_CREATED_NO_EXPIRY = 'pnr_created_no_expiry_returned';

    public const COMPLEX_GUARD_WOULD_DEFER_PUBLIC = 'complex_guard_would_defer_public';

    public const HOST_SELL_REJECTED_UC = 'host_sell_rejected_uc';

    public const HOST_SELL_PENDING_NN = 'host_sell_pending_nn';

    public const NO_FARES_RBD_CARRIER = 'no_fares_rbd_carrier';

    public const BOOKING_CLASS_MISMATCH = 'booking_class_mismatch';

    public const STALE_OR_MISSING_INVENTORY = 'stale_or_missing_inventory';

    public const PROVIDER_APPLICATION_ERROR = 'provider_application_error';

    public const SCHEMA_OR_PAYLOAD_VALIDATION_ERROR = 'schema_or_payload_validation_error';

    public const PNR_REQUIRES_MANUAL_SABRE_PRICING = 'pnr_requires_manual_sabre_pricing';

    public const REVALIDATION_LINKAGE_INCOMPLETE = 'revalidation_linkage_incomplete';

    public const UPDATED_FARE_REQUIRES_ACCEPTANCE = 'updated_fare_requires_acceptance';

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function classifyDryRun(bool $r5PublicWouldDefer, bool $wireContractValid): string
    {
        if ($r5PublicWouldDefer) {
            return self::COMPLEX_GUARD_WOULD_DEFER_PUBLIC;
        }

        if (! $wireContractValid) {
            return self::SCHEMA_OR_PAYLOAD_VALIDATION_ERROR;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function classifySendOutcome(
        bool $pnrCreated,
        bool $expiryStored,
        ?string $errorCode,
        array $safeSummary = [],
    ): string {
        if ($pnrCreated) {
            return $expiryStored ? self::SUCCESS_PNR_CREATED : self::PNR_CREATED_NO_EXPIRY;
        }

        return self::mapFailureClassification($errorCode, $safeSummary);
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    public static function mapFailureClassification(?string $errorCode, array $safeSummary = []): string
    {
        $classified = SabrePnrFailureClassifier::classify($errorCode, $safeSummary);
        $classification = (string) ($classified['classification'] ?? '');

        return match ($classification) {
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_SELL_REJECTED_UC => self::HOST_SELL_REJECTED_UC,
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_SELL_PENDING_NN => self::HOST_SELL_PENDING_NN,
            SabrePnrFailureClassifier::CLASSIFICATION_NO_FARES_RBD_CARRIER => self::NO_FARES_RBD_CARRIER,
            SabrePnrFailureClassifier::CLASSIFICATION_PNR_REQUIRES_MANUAL_SABRE_PRICING => self::PNR_REQUIRES_MANUAL_SABRE_PRICING,
            SabrePnrFailureClassifier::CLASSIFICATION_REVALIDATION_LINKAGE_INCOMPLETE => self::REVALIDATION_LINKAGE_INCOMPLETE,
            SabrePnrFailureClassifier::CLASSIFICATION_BOOKING_CLASS_MISMATCH => self::BOOKING_CLASS_MISMATCH,
            SabrePnrFailureClassifier::CLASSIFICATION_STALE_OR_MISSING_INVENTORY => self::STALE_OR_MISSING_INVENTORY,
            SabrePnrFailureClassifier::CLASSIFICATION_PROVIDER_APPLICATION_ERROR => self::PROVIDER_APPLICATION_ERROR,
            SabrePnrFailureClassifier::CLASSIFICATION_SCHEMA_OR_PAYLOAD_VALIDATION_ERROR => self::SCHEMA_OR_PAYLOAD_VALIDATION_ERROR,
            SabrePnrFailureClassifier::CLASSIFICATION_COMPLEX_DEFERRED => self::COMPLEX_GUARD_WOULD_DEFER_PUBLIC,
            SabrePnrFailureClassifier::CLASSIFICATION_UPDATED_FARE_REQUIRES_ACCEPTANCE => self::UPDATED_FARE_REQUIRES_ACCEPTANCE,
            default => $classification !== ''
                ? $classification
                : self::PROVIDER_APPLICATION_ERROR,
        };
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return list<string>
     */
    public static function sanitizedHostStatuses(array $safeSummary): array
    {
        $parts = [];
        foreach (['response_error_messages', 'application_error_messages', 'host_warning_messages_truncated', 'messages'] as $key) {
            $val = $safeSummary[$key] ?? null;
            if (is_string($val)) {
                $parts[] = $val;
            } elseif (is_array($val)) {
                foreach ($val as $item) {
                    if (is_string($item)) {
                        $parts[] = $item;
                    }
                }
            }
        }

        $text = strtoupper(implode(' ', $parts));
        $statuses = [];
        foreach (['UC', 'NO', 'HX', 'HL', 'UN', 'NN', 'KK'] as $code) {
            if (str_contains($text, 'STATUS CODE '.$code) || preg_match('/\b'.$code.'\b/', $text)) {
                $statuses[] = $code;
            }
        }

        return array_values(array_unique($statuses));
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return array{response_error_codes: list<string>, response_error_messages: list<string>}
     */
    public static function sanitizedErrorDigest(array $safeSummary): array
    {
        $codes = [];
        foreach (['response_error_codes', 'pnr_codes'] as $key) {
            $val = $safeSummary[$key] ?? null;
            if (! is_array($val)) {
                continue;
            }
            foreach (array_slice($val, 0, 12) as $c) {
                if (is_scalar($c)) {
                    $t = trim((string) $c);
                    if ($t !== '') {
                        $codes[] = strlen($t) > 80 ? substr($t, 0, 80) : $t;
                    }
                }
            }
        }

        $messages = [];
        foreach (['response_error_messages', 'host_warning_messages_truncated'] as $key) {
            $val = $safeSummary[$key] ?? null;
            if (! is_array($val)) {
                continue;
            }
            foreach (array_slice($val, 0, 8) as $m) {
                if (! is_string($m)) {
                    continue;
                }
                $t = trim($m);
                if ($t === '') {
                    continue;
                }
                $messages[] = strlen($t) > 220 ? substr($t, 0, 220) : $t;
            }
        }

        return [
            'response_error_codes' => array_values(array_unique($codes)),
            'response_error_messages' => array_values(array_unique($messages)),
        ];
    }
}
