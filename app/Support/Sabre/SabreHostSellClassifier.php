<?php

namespace App\Support\Sabre;

use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreHostErrorClassifier;
use Illuminate\Support\Str;

/**
 * Sabre GDS host sell outcome classification (Passenger Records / EnhancedAirBook diagnostics only).
 */
final class SabreHostSellClassifier
{
    public const CLASSIFIER_VERSION = 'sabre_host_sell_classifier_v1';

    public const OUTCOME_SELL_CONFIRMED = 'sell_confirmed';

    public const OUTCOME_HOST_SELL_REJECTED_UC = 'host_sell_rejected_uc';

    public const OUTCOME_HOST_WAITLIST_OR_HOLD = 'host_waitlist_or_hold';

    public const OUTCOME_HOST_NEED_NEED_STATUS = 'host_need_need_status';

    public const OUTCOME_HOST_NO_ACTION_OR_REJECTED = 'host_no_action_or_rejected';

    public const OUTCOME_HOST_HALT_ON_STATUS = 'host_halt_on_status';

    public const RETRY_NO_RETRY_SAME_OFFER = 'no_retry_same_offer';

    public const RETRY_MANUAL_REVIEW = 'manual_review';

    public const RETRY_WITH_STRATEGY_CHANGE = 'retry_with_strategy_change';

    public const CUSTOMER_HOST_REJECTION_MESSAGE = 'Airline could not confirm this itinerary at the time of booking. Our team will review it, or you may select another available itinerary.';

    public const CUSTOMER_SAME_OFFER_BLOCKED_MESSAGE = 'Airline could not confirm this itinerary. Please select another fare or itinerary.';

    /**
     * @param  array<string, mixed>  $outcome
     */
    public static function customerNoticeForOutcome(array $outcome): ?string
    {
        if (($outcome['live_call_attempted'] ?? false) !== true) {
            return null;
        }

        $status = (string) ($outcome['status'] ?? '');
        if (! in_array($status, ['failed', 'needs_review'], true)) {
            return null;
        }

        $classified = self::classify(array_intersect_key($outcome, array_flip([
            'error_code', 'http_status', 'airline_segment_status', 'halt_on_status_received',
            'pnr_present_in_response_body', 'pnr', 'probable_issue', 'response_error_messages',
            'application_error_messages', 'messages', 'message',
        ])));

        $reason = strtolower(trim((string) ($classified['safe_reason_code'] ?? '')));
        if ($reason === self::OUTCOME_SELL_CONFIRMED || $reason === '') {
            return null;
        }

        if (in_array($reason, [
            self::OUTCOME_HOST_SELL_REJECTED_UC,
            self::OUTCOME_HOST_WAITLIST_OR_HOLD,
            self::OUTCOME_HOST_NEED_NEED_STATUS,
            self::OUTCOME_HOST_NO_ACTION_OR_REJECTED,
            self::OUTCOME_HOST_HALT_ON_STATUS,
        ], true)) {
            return self::CUSTOMER_HOST_REJECTION_MESSAGE;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function classify(array $context): array
    {
        $segmentStatus = strtoupper(trim((string) ($context['airline_segment_status'] ?? '')));
        $pnrPresent = ($context['pnr_present'] ?? false) === true
            || ($context['pnr_present_in_response_body'] ?? false) === true
            || trim((string) ($context['pnr'] ?? '')) !== '';
        $haltOnStatus = ($context['halt_on_status_received'] ?? false) === true;
        $messagesUpper = strtoupper(self::stringifyMessages($context));

        if ($segmentStatus === 'HK' && $pnrPresent) {
            return self::result(
                self::OUTCOME_SELL_CONFIRMED,
                'Airline confirmed segments (HK) and PNR is present.',
                'PNR sell confirmed — proceed with sync/ticketing workflow as configured.',
                self::RETRY_NO_RETRY_SAME_OFFER,
                false,
                ['airline_segment_status:HK', 'pnr_present:true'],
            );
        }

        if ($segmentStatus === 'UC' || self::messagesIndicateStatus($messagesUpper, 'UC')) {
            return self::result(
                self::OUTCOME_HOST_SELL_REJECTED_UC,
                'Sabre could not confirm one or more requested flight segments (UC).',
                'Re-shop/revalidate and choose alternate itinerary.',
                self::RETRY_NO_RETRY_SAME_OFFER,
                true,
                self::buildStatusSignals($context, 'UC', $haltOnStatus),
            );
        }

        if ($segmentStatus === 'HL' || self::messagesIndicateStatus($messagesUpper, 'HL')) {
            return self::result(
                self::OUTCOME_HOST_WAITLIST_OR_HOLD,
                'Airline returned waitlist/hold segment status (HL).',
                'Check carrier status / queue / manual follow-up.',
                self::RETRY_MANUAL_REVIEW,
                true,
                self::buildStatusSignals($context, 'HL', $haltOnStatus),
            );
        }

        if ($segmentStatus === 'NN' || self::messagesIndicateStatus($messagesUpper, 'NN') || $haltOnStatus) {
            $retry = self::isRouteCarrierCertified($context)
                ? self::RETRY_WITH_STRATEGY_CHANGE
                : self::RETRY_NO_RETRY_SAME_OFFER;

            if ($haltOnStatus && ! in_array($segmentStatus, ['NN', 'HL', 'UC', 'NO'], true)) {
                $underlying = $segmentStatus !== '' ? $segmentStatus : 'NN';

                return self::result(
                    self::OUTCOME_HOST_HALT_ON_STATUS,
                    'Sabre HaltOnStatus received during host sell.',
                    self::adminActionForHaltOnStatus($underlying),
                    self::retryPolicyForHaltOnStatus($underlying),
                    true,
                    self::buildStatusSignals($context, $underlying, true),
                );
            }

            return self::result(
                self::OUTCOME_HOST_NEED_NEED_STATUS,
                'Airline returned need/need segment status (NN).',
                'Retry with alternate sell strategy or re-shop.',
                $retry,
                true,
                self::buildStatusSignals($context, 'NN', $haltOnStatus),
            );
        }

        if (in_array($segmentStatus, ['NO', 'HX', 'UN'], true) || self::messagesIndicateStatus($messagesUpper, 'NO')) {
            return self::result(
                self::OUTCOME_HOST_NO_ACTION_OR_REJECTED,
                'Airline did not confirm segments (NO).',
                'Re-shop itinerary.',
                self::RETRY_NO_RETRY_SAME_OFFER,
                true,
                self::buildStatusSignals($context, $segmentStatus !== '' ? $segmentStatus : 'NO', $haltOnStatus),
            );
        }

        $legacy = SabreHostErrorClassifier::classify($context);

        return [
            'safe_reason_code' => (string) ($legacy['safe_reason_code'] ?? SabreHostErrorClassifier::REASON_UNKNOWN),
            'safe_summary' => (string) ($legacy['safe_summary'] ?? ''),
            'recommended_admin_action' => (string) ($legacy['recommended_admin_action'] ?? ''),
            'retry_policy' => (string) ($legacy['retry_policy'] ?? SabreHostErrorClassifier::RETRY_NO_AUTO_RETRY),
            'manual_review_required' => (bool) ($legacy['manual_review_required'] ?? true),
            'source_layer' => (string) ($legacy['source_layer'] ?? SabreHostErrorClassifier::LAYER_UNKNOWN),
            'matched_signals' => is_array($legacy['matched_signals'] ?? null) ? $legacy['matched_signals'] : [],
            'classifier_version' => self::CLASSIFIER_VERSION,
            'host_error_family' => SabreHostErrorClassifier::hostErrorFamilyForReason((string) ($legacy['safe_reason_code'] ?? '')),
            'segment_status' => $segmentStatus !== '' ? $segmentStatus : null,
            'halt_on_status_received' => $haltOnStatus,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $resultSlice
     * @return array<string, mixed>
     */
    public static function buildPersistedSlice(array $context, array $resultSlice = []): array
    {
        $classified = self::classify($context);
        $slice = array_merge($classified, [
            'admin_summary' => $classified['recommended_admin_action'] ?? '',
            'recorded_at' => now()->toIso8601String(),
        ]);

        foreach ([
            'live_call_attempted', 'booking_schema', 'payload_schema', 'segment_count', 'passenger_count',
            'pnr_present', 'halt_on_status_received',
        ] as $key) {
            if (! array_key_exists($key, $resultSlice)) {
                continue;
            }
            $value = $resultSlice[$key];
            if ($value === null || $value === '') {
                continue;
            }
            $slice[$key] = $value;
        }

        return $slice;
    }

    /**
     * @return list<string>
     */
    public static function definitiveRejectionReasonCodes(): array
    {
        return [
            self::OUTCOME_HOST_SELL_REJECTED_UC,
            self::OUTCOME_HOST_NO_ACTION_OR_REJECTED,
            self::OUTCOME_HOST_HALT_ON_STATUS,
        ];
    }

    public static function isDefinitiveSameOfferRejection(string $safeReasonCode, ?string $segmentStatus = null): bool
    {
        $code = strtolower(trim($safeReasonCode));
        if (in_array($code, [self::OUTCOME_HOST_SELL_REJECTED_UC, self::OUTCOME_HOST_NO_ACTION_OR_REJECTED], true)) {
            return true;
        }

        if ($code === self::OUTCOME_HOST_HALT_ON_STATUS) {
            $status = strtoupper(trim((string) $segmentStatus));

            return in_array($status, ['UC', 'NO', 'HX', 'UN'], true);
        }

        return false;
    }

    /**
     * @param  list<string>  $signals
     * @return array<string, mixed>
     */
    protected static function result(
        string $safeReasonCode,
        string $safeSummary,
        string $recommendedAdminAction,
        string $retryPolicy,
        bool $manualReviewRequired,
        array $signals,
    ): array {
        return [
            'safe_reason_code' => $safeReasonCode,
            'safe_summary' => $safeSummary,
            'recommended_admin_action' => $recommendedAdminAction,
            'retry_policy' => $retryPolicy,
            'manual_review_required' => $manualReviewRequired,
            'source_layer' => SabreHostErrorClassifier::LAYER_AIRBOOK_SELL,
            'matched_signals' => $signals,
            'classifier_version' => self::CLASSIFIER_VERSION,
            'host_error_family' => self::hostErrorFamilyForReason($safeReasonCode),
            'segment_status' => self::segmentStatusFromSignals($signals),
            'halt_on_status_received' => in_array('halt_on_status_received:true', $signals, true),
        ];
    }

    public static function hostErrorFamilyForReason(string $safeReasonCode): ?string
    {
        return match (strtolower(trim($safeReasonCode))) {
            self::OUTCOME_SELL_CONFIRMED => 'SELL_CONFIRMED',
            self::OUTCOME_HOST_SELL_REJECTED_UC => SabreHostErrorClassifier::HOST_ERROR_FAMILY_UC_SEGMENT_STATUS,
            self::OUTCOME_HOST_WAITLIST_OR_HOLD => 'HOST_WAITLIST_OR_HOLD',
            self::OUTCOME_HOST_NEED_NEED_STATUS => SabreHostErrorClassifier::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
            self::OUTCOME_HOST_NO_ACTION_OR_REJECTED => SabreHostErrorClassifier::HOST_ERROR_FAMILY_UC_SEGMENT_STATUS,
            self::OUTCOME_HOST_HALT_ON_STATUS => SabreHostErrorClassifier::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
            default => SabreHostErrorClassifier::hostErrorFamilyForReason($safeReasonCode),
        };
    }

    protected static function adminActionForHaltOnStatus(string $segmentStatus): string
    {
        return match (strtoupper($segmentStatus)) {
            'UC' => 'Re-shop/revalidate and choose alternate itinerary.',
            'NO', 'HX', 'UN' => 'Re-shop itinerary.',
            'HL' => 'Check carrier status / queue / manual follow-up.',
            'NN' => 'Retry with alternate sell strategy or re-shop.',
            default => 'Review host sell diagnostics and re-shop if needed.',
        };
    }

    protected static function retryPolicyForHaltOnStatus(string $segmentStatus): string
    {
        return match (strtoupper($segmentStatus)) {
            'HL' => self::RETRY_MANUAL_REVIEW,
            'NN' => self::RETRY_WITH_STRATEGY_CHANGE,
            'UC', 'NO', 'HX', 'UN' => self::RETRY_NO_RETRY_SAME_OFFER,
            default => self::RETRY_NO_RETRY_SAME_OFFER,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected static function buildStatusSignals(array $context, string $status, bool $haltOnStatus): array
    {
        $signals = ['airline_segment_status:'.$status];
        if ($haltOnStatus || ($context['halt_on_status_received'] ?? false) === true) {
            $signals[] = 'halt_on_status_received:true';
        }

        return array_values(array_unique($signals));
    }

    /**
     * @param  list<string>  $signals
     */
    protected static function segmentStatusFromSignals(array $signals): ?string
    {
        foreach ($signals as $signal) {
            if (str_starts_with($signal, 'airline_segment_status:')) {
                return strtoupper(substr($signal, strlen('airline_segment_status:')));
            }
        }

        return null;
    }

    protected static function messagesIndicateStatus(string $messagesUpper, string $status): bool
    {
        return Str::contains($messagesUpper, ['STATUS CODE '.$status, 'RETURNED STATUS CODE '.$status]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected static function isRouteCarrierCertified(array $context): bool
    {
        $cert = strtolower(trim((string) ($context['controlled_pnr_certification_status'] ?? '')));

        return $cert !== ''
            && $cert !== SabreCertifiedRouteSelector::CONTROLLED_PNR_HOST_NOOP_BLOCKED
            && $cert !== SabreCertifiedRouteSelector::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected static function stringifyMessages(array $context): string
    {
        $parts = [];
        foreach (['response_error_messages', 'application_error_messages', 'messages', 'message', 'host_warning_messages_truncated'] as $key) {
            $val = $context[$key] ?? null;
            if (is_string($val)) {
                $parts[] = $val;
            } elseif (is_array($val)) {
                foreach ($val as $item) {
                    if (is_scalar($item)) {
                        $parts[] = (string) $item;
                    }
                }
            }
        }

        return implode(' ', $parts);
    }
}
